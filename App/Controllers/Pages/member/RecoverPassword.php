<?php

namespace PressDo\App\Controllers\Pages\Member;

use PressDo\App\Core\{Controller, Response};
use PressDo\App\Helpers\{Config, Languages};
use PressDo\App\Http\Security\EmailVerificationPurpose;
use PressDo\App\Models\Member;

class RecoverPassword extends Controller
{
    private const SESSION_KEY = 'password_reset_code';

    public function makeData(): array
    {
        header('Referrer-Policy: no-referrer');
        if (!empty($this->session['member'])) {
            Response::redirect('/');
        }

        $page = [
            'view_name' => 'recover_password',
            'title' => Languages::get('page', 'recover_password'),
            'data' => ['error' => null, 'step' => 0],
        ];
        $purpose = EmailVerificationPurpose::PasswordReset;
        $code = $this->request->queryOptionalString('x');
        if ($code !== null && $this->request->isMethod('GET')) {
            unset($this->session[self::SESSION_KEY]);
            if (Member::verifyEmailCode($code, $purpose) === null) {
                unset($_SESSION[self::SESSION_KEY]);
                $page['data']['error'] = 'err_invalid_code';
                return $page;
            }

            $this->session[self::SESSION_KEY] = $code;
            $_SESSION = $this->session;
            Response::redirect('/member/recover_password');
        }

        if ($this->request->isMethod('POST') && $this->request->hasPost('email')) {
            if (!self::validateCaptcha($this->request->postString($this->api_config['captcha_token_name'] ?? ''))) {
                $page['data']['error'] = 'captcha_failed';
                return $page;
            }

            unset($this->session[self::SESSION_KEY]);
            $email = trim($this->request->postString('email'));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 256) {
                $member = Member::exist(email: $email);
                if ($member !== false) {
                    $this->sendResetLink($email, $member['username'], $purpose);
                }
            }

            $page['data']['step'] = 1;
            return $page;
        }

        $pending = $this->session[self::SESSION_KEY] ?? null;
        if (is_string($pending) && Member::verifyEmailCode($pending, $purpose) !== null) {
            $page['data']['step'] = 2;
        } elseif ($this->request->isMethod('POST') || $pending !== null) {
            unset($this->session[self::SESSION_KEY]);
            $page['data']['error'] = 'err_invalid_code';
            return $page;
        }

        if ($page['data']['step'] !== 2 || !$this->request->isMethod('POST')) {
            return $page;
        }

        $password = $this->request->postString('password');
        if (mb_strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
            $page['data']['error'] = 'err_password_length';
        } elseif ($password !== $this->request->postString('password2')) {
            $page['data']['error'] = 'err_wrong_password2';
        } elseif (!Member::completePasswordReset($pending, $password)) {
            unset($this->session[self::SESSION_KEY]);
            $page['data'] = ['error' => 'err_invalid_code', 'step' => 0];
        } else {
            unset($this->session[self::SESSION_KEY]);
            $_SESSION = $this->session;
            Response::redirect('/member/login', 303);
        }

        return $page;
    }

    private function sendResetLink(string $email, string $username, EmailVerificationPurpose $purpose): void
    {
        $token = Member::issueEmailCode($email, $this->session['ip'], $purpose);
        if ($token === null) {
            return;
        }

        $language = Languages::get('mail');
        $site = (string) Config::get('wiki.site_name');
        $url = rtrim((string) Config::get('wiki.canonical_url'), '/') . '/member/recover_password?x=' . $token;
        $message = sprintf(
            $language['greeting'] . $language['recover'] . '<br><br>' . $language['request_ip'],
            htmlspecialchars($site),
            htmlspecialchars($url),
            htmlspecialchars($this->session['ip']),
            htmlspecialchars($username),
        );
        try {
            if (self::sendMail($email, sprintf($language['recover_title'], $site, $username), $message)) {
                return;
            }
        } catch (\Exception) {
            // The credential and mail infrastructure stay out of the public response.
        }
        error_log('Password recovery email delivery failed.');
    }
}
