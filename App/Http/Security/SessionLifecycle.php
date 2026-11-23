<?php

declare(strict_types=1);

namespace PressDo\App\Http\Security;

use PressDo\App\Core\Request;
use RuntimeException;

final class SessionLifecycle
{
    public static function start(Request $request, string $canonicalUrl): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = strtolower($request->serverString('HTTPS'));
        $secure = in_array($https, ['on', '1'], true)
            || strtolower((string) parse_url($canonicalUrl, PHP_URL_SCHEME)) === 'https'
            || (bool) ini_get('session.cookie_secure');

        if (!session_start([
            'use_only_cookies' => 1,
            'use_strict_mode' => 1,
            'use_trans_sid' => 0,
            'cookie_lifetime' => 0,
            'cookie_path' => '/',
            'cookie_domain' => '',
            'cookie_secure' => $secure,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ])) {
            throw new RuntimeException('Unable to start the session.');
        }
    }

    /**
     * @param array<string, mixed> $session
     * @param array<string, mixed> $identity
     * @return array<string, mixed>
     */
    public static function authenticate(array $session, array $identity): array
    {
        // Rotate before persisting the identity, while the previous ID still belongs to a guest.
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Unable to rotate the session ID.');
        }

        return $identity + array_intersect_key($session, ['ip' => true, 'ua' => true]);
    }

    public static function destroy(): void
    {
        $cookie = session_get_cookie_params();
        $name = session_name();
        if (!is_string($name)) {
            throw new RuntimeException('Unable to read the session cookie name.');
        }

        setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'],
        ]);

        $_SESSION = [];
        if (!session_destroy()) {
            throw new RuntimeException('Unable to destroy the session.');
        }
    }
}
