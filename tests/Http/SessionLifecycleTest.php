<?php

declare(strict_types=1);

use PressDo\App\Core\Request;
use PressDo\App\Http\Security\SessionLifecycle;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function checkSessionLifecycle(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

SessionLifecycle::start(new Request(), 'https://wiki.example.test');
$guestId = session_id();
$cookie = session_get_cookie_params();
checkSessionLifecycle(
    $guestId !== '' && $cookie['secure'] && $cookie['httponly']
        && $cookie['samesite'] === 'Lax' && $cookie['domain'] === '' && $cookie['path'] === '/',
    'An HTTPS installation should use a host-only, Secure, HttpOnly session cookie.',
);
checkSessionLifecycle(ini_get('session.use_strict_mode') === '1', 'Strict session ID handling must be enabled.');

$_SESSION = SessionLifecycle::authenticate(
    ['temp' => ['uuid' => 'pending'], 'ip' => '127.0.0.1', 'edittoken' => 'guest-token'],
    ['uuid' => 'member', 'member' => ['username' => 'member']],
);
checkSessionLifecycle(session_id() !== $guestId && session_id() !== '', 'Authentication should rotate the guest ID.');
checkSessionLifecycle($_SESSION === [
    'uuid' => 'member',
    'member' => ['username' => 'member'],
    'ip' => '127.0.0.1',
], 'Authentication should rebuild the session with the verified identity.');

SessionLifecycle::destroy();
checkSessionLifecycle($_SESSION === [] && session_status() === PHP_SESSION_NONE, 'Logout should clear the session.');
echo 'Session lifecycle tests passed.' . PHP_EOL;
