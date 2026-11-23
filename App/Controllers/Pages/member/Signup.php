<?php

namespace PressDo\App\Controllers\Pages\Member;

use PressDo\App\Core\{Controller, Response};
use PressDo\App\Helpers\{Config, Languages};
use PressDo\App\Http\Security\EmailVerificationPurpose;
use PressDo\App\Models\Member;

class Signup extends Controller
{
    private const SESSION_KEY = 'signup_code';

    public function makeData(): array
    {
        header('Referrer-Policy: no-referrer');
        if (!empty($this->session['member'])) {
            Response::redirect('/');
        }

        $page = [
            'view_name' => 'signup',
            'title' => Languages::get('page', 'signup'),
            'data' => ['error' => null, 'step' => 0],
        ];
        $purpose = EmailVerificationPurpose::Signup;
        $ip = $this->request->serverString('REMOTE_ADDR');
        $code = $this->request->queryOptionalString('x');
        if ($code !== null && $this->request->isMethod('GET')) {
            unset($this->session[self::SESSION_KEY]);
            if (Member::verifyEmailCode($code, $purpose, $ip) === null) {
                unset($_SESSION[self::SESSION_KEY]);
                $page['data']['error'] = 'err_invalid_code';
                return $page;
            }

            $this->session[self::SESSION_KEY] = $code;
            $_SESSION = $this->session;
            Response::redirect('/member/signup');
        }

        if ($this->request->isMethod('POST') && $this->request->hasPost('email')) {
            $email = trim($this->request->postString('email'));
            $host = strtolower(substr(strrchr($email, '@') ?: '', 1));
            $allowed = Config::get('member.whitelist_email_host');
            $allowed = is_array($allowed) ? $allowed : [$allowed];
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 256 || !in_array($host, $allowed, true)) {
                $page['data']['error'] = 'err_mail_whitelist';
            } elseif (!self::validateCaptcha($this->request->postString($this->api_config['captcha_token_name'] ?? ''))) {
                $page['data']['error'] = 'captcha_failed';
            } else {
                unset($this->session[self::SESSION_KEY]);
                $page['data']['step'] = 1;
                $this->sendSignupLink($email, $ip, $purpose);
            }

            return $page;
        }

        $pending = $this->session[self::SESSION_KEY] ?? null;
        if (is_string($pending) && Member::verifyEmailCode($pending, $purpose, $ip) !== null) {
            $page['data']['step'] = 2;
        } elseif ($this->request->isMethod('POST') || $pending !== null) {
            unset($this->session[self::SESSION_KEY]);
            $page['data']['error'] = 'err_invalid_code';
            return $page;
        }

        if ($page['data']['step'] !== 2 || !$this->request->isMethod('POST')) {
            return $page;
        }

        $username = $this->request->postString('username');
        $password = $this->request->postString('password');
        if (preg_match('/\A[0-9A-Za-z_]{1,32}\z/', $username) !== 1 || Member::exist($username) !== false) {
            $page['data']['error'] = 'err_invalid_username';
        } elseif (mb_strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
            $page['data']['error'] = 'err_password_length';
        } elseif ($password !== $this->request->postString('password2')) {
            $page['data']['error'] = 'err_wrong_password2';
        } elseif (!Member::completeSignup($pending, $ip, $username, $password)) {
            unset($this->session[self::SESSION_KEY]);
            $page['data'] = ['error' => 'err_invalid_code', 'step' => 0];
        } else {
            unset($this->session[self::SESSION_KEY]);
            $page['data']['step'] = 3;
        }

        return $page;
    }

    private function sendSignupLink(string $email, string $ip, EmailVerificationPurpose $purpose): void
    {
        $existing = Member::exist(email: $email) !== false;
        $code = $existing ? null : Member::issueEmailCode($email, $ip, $purpose);
        if (!$existing && $code === null) {
            return;
        }

        $language = Languages::get('mail');
        $site = (string) Config::get('wiki.site_name');
        $url = rtrim((string) Config::get('wiki.canonical_url'), '/') . '/member/signup?x=' . $code;
        $message = sprintf(
            $language['greeting'] . $language['signup'] . '<br>'
                . $language[$existing ? 'signup_duplicate' : 'signup_success'] . '<br>' . $language['request_ip'],
            htmlspecialchars($site),
            htmlspecialchars($url),
            htmlspecialchars($ip),
        );
        try {
            if (self::sendMail($email, sprintf($language['signup_title'], $site), $message)) {
                return;
            }
        } catch (\Exception) {
            // The public response stays the same when mail delivery fails.
        }
        error_log('Signup verification email delivery failed.');
    }
}
