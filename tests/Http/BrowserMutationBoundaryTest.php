<?php

declare(strict_types=1);

use PressDo\App\Core\Model;
use PressDo\App\Core\Request;
use PressDo\App\Helpers\Database;
use PressDo\App\Helpers\DefaultConfig;
use PressDo\App\Http\Security\CsrfTokenManager;
use PressDo\App\Http\Security\LoginChallenge;
use PressDo\App\Http\Security\SessionLifecycle;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo 'Browser mutation boundary tests skipped (pdo_sqlite unavailable).' . PHP_EOL;
    exit(0);
}

if (($argv[1] ?? null) === '--fixture') {
    [, , $requestUri, $method, $tokenMode] = $argv;
    $config = json_decode(file_get_contents($root . '/templates/config.json'), true, flags: JSON_THROW_ON_ERROR);
    $config['database.type'] = 'sqlite';
    $config['database.name'] = ':memory:';
    $config['wiki.mark'] = 'NamuMark';
    (new ReflectionProperty(DefaultConfig::class, 'DefConfig'))->setValue(null, $config);
    $database = Database::getInstance();
    $database->exec(file_get_contents($root . '/templates/database_scheme.sqlite.sql'));

    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['REQUEST_URI'] = $requestUri;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = ['text' => 'Preview fixture', 'title' => 'Sample'];
    SessionLifecycle::start(Request::fromGlobals(), '');
    $token = (new CsrfTokenManager())->issueBrowserToken($_SESSION);
    if ($tokenMode === 'valid') {
        $_POST['_csrf'] = $token;
    }

    if (str_starts_with($tokenMode, 'factor-')) {
        $uuid = '00000000-0000-4000-8000-000000000001';
        $database->prepare('INSERT INTO member (uuid, username, email) VALUES (?, ?, ?)')
            ->execute([Model::uuid2bin($uuid), 'fixture', 'fixture@example.test']);
        $_SESSION['temp'] = ['uuid' => $uuid];
        $_SESSION['do2fa'] = 'email';
        $_SESSION['pin'] = '123456';
        $_SESSION['factor_view'] = ['force_2fa' => true, 'use_totp' => false, 'use_webauthn' => false, 'otp_email' => 'fixture@example.test'];
        LoginChallenge::begin($_SESSION, $tokenMode === 'factor-expired' ? time() - 601 : time());
        $_POST['_csrf'] = $token;
        $_POST['pin'] = '123456';
    }

    register_shutdown_function(static function (): void {
        echo '\nSTATUS=' . (http_response_code() ?: 200);
    });
    require $root . '/public/index.php';
    exit;
}

/** @return array{0: string, 1: int} */
function browserBoundaryRequest(string $path, string $method, string $tokenMode = 'absent'): array
{
    $process = proc_open(
        [PHP_BINARY, __FILE__, '--fixture', $path, $method, $tokenMode],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start a browser-boundary fixture.');
    }

    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0 || $errors !== '' || preg_match('/STATUS=(\d+)\z/', $output, $match) !== 1) {
        throw new RuntimeException('Request fixture failed for ' . $path . ': ' . $errors);
    }

    return [$output, (int) $match[1]];
}

foreach ([
    ['/member/logout', 'GET', 'absent', 405],
    ['/member/star/Sample', 'GET', 'absent', 405],
    ['/member/unstar/Sample', 'HEAD', 'absent', 405],
    ['/mark_troll/Sample', 'GET', 'absent', 405],
    ['/edit_request/request/accept', 'GET', 'absent', 405],
    ['/edit_request/request/close', 'GET', 'absent', 405],
    ['/edit_request/request/reopen', 'GET', 'absent', 405],
    ['/api/preview', 'POST', 'absent', 403],
    ['/admin/grant', 'POST', 'absent', 403],
    ['/member/login', 'PUT', 'absent', 405],
] as [$path, $method, $tokenMode, $expected]) {
    [, $status] = browserBoundaryRequest($path, $method, $tokenMode);
    if ($status !== $expected) {
        throw new RuntimeException($method . ' ' . $path . ' returned an unexpected status.');
    }
}

[$preview, $status] = browserBoundaryRequest('/api/preview', 'POST', 'valid');
if ($status !== 200 || !str_contains($preview, '<p>Preview fixture</p>')) {
    throw new RuntimeException('Same-session preview POST should render.');
}

[$login, $status] = browserBoundaryRequest('/member/login', 'GET');
if ($status !== 200 || !preg_match('/name="_csrf" value="[a-f0-9]{64}"/', $login)) {
    throw new RuntimeException('The login form must include its session-bound token.');
}

[, $status] = browserBoundaryRequest('/member/login', 'POST', 'factor-valid');
if ($status !== 302) {
    throw new RuntimeException('A current second factor must complete login.');
}

[$expired, $status] = browserBoundaryRequest('/member/login', 'POST', 'factor-expired');
if ($status !== 200 || !str_contains($expired, '시도 횟수를 초과했습니다')) {
    throw new RuntimeException('An expired second factor must restart login.');
}

echo 'Browser mutation boundary tests passed.' . PHP_EOL;
