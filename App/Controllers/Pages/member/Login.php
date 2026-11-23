<?php
namespace PressDo\App\Controllers\Pages\Member;

use PressDo\App\Models\Member;
use PressDo\App\Core\{Controller,Response};
use PressDo\App\Helpers\{Languages,Config};
use PressDo\App\Http\Security\{LoginChallenge, SessionLifecycle};
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use OTPHP\TOTP;

class Login extends Controller
{
    public function makeData(): array
    {
        if (!empty($this->session['member'])) {
            Header('Location: /');
            exit;
        }

        $page = [
            'view_name' => 'login',
            'title' => Languages::get('page', 'login'),
            'data' => [
                'redirect' => $this->request->queryString('redirect')
            ],
            'menus' => [],
            'customData' => []
        ];

        $pendingUser = $this->session['temp']['uuid'] ?? null;
        if (is_string($pendingUser)) {
            $now = time();
            if (!LoginChallenge::canAttempt($this->session, $now)) {
                $this->clearSecondFactor();
                $page['data']['error'] = 'err_2fa_expired';
                return $page;
            }

            if ($this->request->isMethod('POST')) {
                if ($this->request->hasPost('challenge') && ($this->session['do2fa'] ?? null) === 'webauthn') {
                    $this->verifyWebAuthn($page);
                } elseif (preg_match('/\A[0-9]{6}\z/', $this->request->postString('pin')) === 1) {
                    $this->verifyPin($page);
                } else {
                    $page['data']['error'] = 'err_invalid_pin';
                }

                if (!isset($page['data']['error'])) {
                    $this->finishLogin(true);
                }
                if (!LoginChallenge::recordFailure($this->session, $now)) {
                    $this->clearSecondFactor();
                    $page['data']['error'] = 'err_2fa_expired';
                    return $page;
                }
            }

            $page['data'] += $this->session['factor_view'] ?? [];

            return $page;
        }

        if (!$this->request->hasPost('username') || !$this->request->hasPost('password')) {
            return $page;
        }
        if (!self::validateCaptcha($this->request->postString($this->api_config['captcha_token_name'] ?? ''))) {
            $page['data']['error'] = 'captcha_failed';
            return $page;
        }

        $credentials = Member::checkMember($this->request->postString('username'), $this->request->postString('password'));
        if ($credentials === false) {
            $page['data']['error'] = 'err_invalid_member';
            return $page;
        }

        $this->clearSecondFactor();
        $uuid = $credentials['uuid'];
        $this->session['temp'] = ['uuid' => $uuid];
        $this->session['szczecin'] = $this->request->hasPost('autologin');
        if (Member::checkCookie('podgorica', $this->request->cookieString('podgorica'), $uuid) !== null) {
            $this->finishLogin(false);
        }

        LoginChallenge::begin($this->session, time());
        $this->setup2fa($uuid, $page['data']);

        return $page;
    }

    private function clearSecondFactor(): void
    {
        unset(
            $this->session['temp'],
            $this->session['second_factor'],
            $this->session['factor_view'],
            $this->session['do2fa'],
            $this->session['pin'],
            $this->session['challenge'],
            $this->session['szczecin'],
        );
    }

    private function setup2fa(string $uuid, array &$data): void
    {
        $user = Member::getUserInfo($uuid);
        if (!is_array($user) || !is_string($user['email'] ?? null)) {
            $this->clearSecondFactor();
            $data['error'] = 'err_invalid_member';
            return;
        }

        $passkeys = Member::getUserWebauthn($uuid);
        $hasTotp = !empty($user['totp_secret']);
        $hasWebAuthn = $hasTotp && $passkeys !== [];
        $this->session['do2fa'] = $hasWebAuthn ? 'webauthn' : ($hasTotp ? 'totp' : 'email');

        $view = [
            'force_2fa' => true,
            'use_totp' => $hasTotp,
            'use_webauthn' => $hasWebAuthn,
        ];
        if ($hasWebAuthn) {
            $webauthn = new WebAuthn(Config::get('wiki.site_name'), Config::get('wiki.domain'));
            $ids = [];
            foreach ($passkeys as $passkey) {
                $stored = json_decode($passkey['client_data'], true);
                $id = is_array($stored) && is_string($stored['credentialId'] ?? null)
                    ? base64_decode($stored['credentialId'], true)
                    : false;
                if (is_string($id) && $id !== '') {
                    $ids[] = $id;
                }
            }

            if ($ids === []) {
                $this->session['do2fa'] = 'totp';
                $view['use_webauthn'] = false;
            } else {
                $view['webauthn_arg'] = json_encode($webauthn->getGetArgs($ids), JSON_THROW_ON_ERROR);
                $this->session['challenge'] = $webauthn->getChallenge();
            }
        } elseif (!$hasTotp) {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $this->session['pin'] = $pin;
            $lang = Languages::get('mail');
            $site = (string) Config::get('wiki.site_name');
            $view['otp_email'] = $user['email'];
            $body = sprintf($lang['greeting'] . $lang['new_login'] . $lang['request_ip'], htmlspecialchars($site), $pin, htmlspecialchars($this->session['ip']));
            try {
                $sent = self::sendMail($user['email'], sprintf($lang['new_login_title'], $site), $body);
            } catch (\Exception) {
                $sent = false;
            }
            if (!$sent) {
                $this->clearSecondFactor();
                $data['error'] = 'err_mail_not_send';
                return;
            }
        }

        $this->session['factor_view'] = $view;
        $data += $view;
    }

    private function verifyWebAuthn(array &$page): void
    {
        $challengeInput = $this->request->postScalarString('challenge');
        $data = $challengeInput !== null && strlen($challengeInput) <= 196_608
            ? json_decode($challengeInput, true)
            : null;
        $response = is_array($data) ? ($data['response'] ?? null) : null;
        if (!is_array($response) || !is_string($data['id'] ?? null)) {
            $page['data']['error'] = 'err_invalid_pin';
            return;
        }

        $decoded = [];
        foreach (['clientDataJSON', 'authenticatorData', 'signature'] as $field) {
            $value = $response[$field] ?? null;
            $bytes = is_string($value) && strlen($value) <= 65_536 ? base64_decode($value, true) : false;
            if ($bytes === false || $bytes === '') {
                $page['data']['error'] = 'err_invalid_pin';
                return;
            }
            $decoded[$field] = $bytes;
        }

        $uuid = $this->session['temp']['uuid'];
        $challenge = $this->session['challenge'] ?? null;
        $id = base64_decode($data['id'], true);
        if ($id === false || $id === '' || $challenge === null) {
            $page['data']['error'] = 'err_invalid_pin';
            return;
        }

        foreach (Member::getUserWebauthn($uuid) as $passkey) {
            $stored = json_decode($passkey['client_data'], true);
            if (!is_array($stored) || !is_string($stored['credentialId'] ?? null)
                || !is_string($stored['credentialPublicKey'] ?? null)
                || base64_decode($stored['credentialId'], true) !== $id) {
                continue;
            }

            $webauthn = new WebAuthn(Config::get('wiki.site_name'), Config::get('wiki.domain'));
            try {
                $webauthn->processGet(
                    $decoded['clientDataJSON'],
                    $decoded['authenticatorData'],
                    $decoded['signature'],
                    $stored['credentialPublicKey'],
                    $challenge,
                );
                Member::logWebauthnUsage($uuid, $passkey['name']);
                return;
            } catch (WebAuthnException) {
                break;
            }
        }

        $page['data']['error'] = 'err_invalid_pin';
    }

    private function verifyPin(array &$page): void
    {
        $mode = $this->session['do2fa'] ?? null;
        $pin = $this->request->postString('pin');
        $valid = false;
        if ($mode === 'totp' || $mode === 'webauthn') {
            $user = Member::getUserInfo($this->session['temp']['uuid']);
            if (is_string($user['totp_secret'] ?? null) && $user['totp_secret'] !== '') {
                $valid = TOTP::createFromSecret($user['totp_secret'])->verify($pin);
            }
        } elseif ($mode === 'email' && is_string($this->session['pin'] ?? null)) {
            $valid = hash_equals($this->session['pin'], $pin);
        }

        if (!$valid) {
            $page['data']['error'] = 'err_invalid_pin';
        }
    }

    private function finishLogin(bool $verifiedSecondFactor): never
    {
        $remember = ($this->session['szczecin'] ?? false) === true;
        $trust = $verifiedSecondFactor && $this->request->hasPost('trust');
        $uuid = $this->session['temp']['uuid'];
        $member = Member::login($uuid, $this->session['ip'], $this->request->serverString('HTTP_USER_AGENT'));
        $identity = self::getMemberData($uuid, $member['email'], $member['username'], $member['skin']);
        $this->session = SessionLifecycle::authenticate($this->session, $identity);

        if ($remember) {
            setcookie(
                'szczecin',
                Member::saveCookies($this->session['uuid'], 'szczecin', 31536000),
                self::getCookieOptions(31536000)
            );
        }
        if ($trust) {
            setcookie(
                'podgorica',
                Member::saveCookies($this->session['uuid'], 'podgorica', 34560000),
                self::getCookieOptions(34560000)
            );
        }
        
        // 로그인 성공
        $_SESSION = $this->session;
        Response::redirect($this->request->queryString('redirect', '/'));
    }
}
