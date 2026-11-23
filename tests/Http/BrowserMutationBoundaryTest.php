<?php

declare(strict_types=1);

use PressDo\App\Core\Model;
use PressDo\App\Core\Request;
use PressDo\App\Helpers\Database;
use PressDo\App\Helpers\DefaultConfig;
use PressDo\App\Http\Security\CsrfTokenManager;
use PressDo\App\Http\Security\EmailVerificationPurpose;
use PressDo\App\Http\Security\LoginChallenge;
use PressDo\App\Http\Security\LoginRateLimiter;
use PressDo\App\Http\Security\LoginRateScope;
use PressDo\App\Http\Security\SessionLifecycle;
use PressDo\App\Infrastructure\Database\SchemaMigrator;
use PressDo\App\Models\Member;

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
    (new SchemaMigrator($database))->migrate();

    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['REQUEST_URI'] = $requestUri;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = ['text' => 'Preview fixture', 'title' => 'Sample'];
    SessionLifecycle::start(Request::fromGlobals(), '');
    $token = (new CsrfTokenManager())->issueBrowserToken($_SESSION);
    if ($tokenMode === 'valid') {
        $_POST['_csrf'] = $token;
    }

    if (str_starts_with($tokenMode, 'factor-') || str_starts_with($tokenMode, 'session-') || $tokenMode === 'password-rate') {
        $uuid = '00000000-0000-4000-8000-000000000001';
        $email = 'fixture@example.test';
        $passwordHash = password_hash('fixture-password', PASSWORD_BCRYPT, ['cost' => 4]);
        $database->prepare('INSERT INTO member (uuid, username, email, password) VALUES (?, ?, ?, ?)')
            ->execute([Model::uuid2bin($uuid), 'fixture', $email, $passwordHash]);
        $credential = SessionLifecycle::credential($passwordHash);

        if (str_starts_with($tokenMode, 'factor-')) {
            $_SESSION['temp'] = ['uuid' => $uuid, 'credential' => $credential];
            $_SESSION['do2fa'] = 'email';
            $_SESSION['pin'] = '123456';
            $_SESSION['factor_view'] = ['force_2fa' => true, 'use_totp' => false, 'use_webauthn' => false, 'otp_email' => $email];
            LoginChallenge::begin($_SESSION, $tokenMode === 'factor-expired' ? time() - 601 : time());
            $_POST['pin'] = '123456';
        } elseif (str_starts_with($tokenMode, 'session-')) {
            $_SESSION['uuid'] = $uuid;
            $_SESSION['member'] = ['username' => 'fixture'];
            if ($tokenMode !== 'session-legacy') {
                $_SESSION['credential'] = $credential;
            }
        } else {
            $_POST['username'] = $email;
            $_POST['password'] = 'fixture-password';
        }
        if ($tokenMode === 'factor-rate' || $tokenMode === 'password-rate') {
            $limiter = new LoginRateLimiter($database);
            $scope = $tokenMode === 'factor-rate' ? LoginRateScope::FactorAccount : LoginRateScope::PasswordAccount;
            $identity = $tokenMode === 'factor-rate' ? $uuid : 'member:' . $uuid;
            [$attempts, $window] = $scope->policy();
            $now = time();
            // Avoid seeding a window that will end during the request fixture.
            if ($now % $window === $window - 1) {
                sleep(1);
                $now = time();
            }
            for ($attempt = 0; $attempt < $attempts; ++$attempt) {
                $limiter->reserve($scope, $identity, $now);
            }
        }
        if (str_ends_with($tokenMode, '-reset')) {
            $reset = Member::issueEmailCode($email, '127.0.0.1', EmailVerificationPurpose::PasswordReset);
            if ($reset === null || !Member::completePasswordReset($reset, 'new-fixture-password')) {
                throw new RuntimeException('Unable to reset the fixture member password.');
            }
        }
        $_POST['_csrf'] = $token;
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
    ['/api/preview', 'POST', 'session-valid', 200],
    ['/api/preview', 'POST', 'session-reset', 403],
    ['/api/preview', 'POST', 'session-legacy', 403],
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

foreach (['factor-expired', 'factor-reset'] as $factor) {
    [$expired, $status] = browserBoundaryRequest('/member/login', 'POST', $factor);
    if ($status !== 200 || !str_contains($expired, '시도 횟수를 초과했습니다')) {
        throw new RuntimeException('An expired or revoked second factor must restart login.');
    }
}

foreach (['factor-rate', 'password-rate'] as $limited) {
    [$login, $status] = browserBoundaryRequest('/member/login', 'POST', $limited);
    if ($status !== 429 || !str_contains($login, '로그인 시도가 너무 많습니다')) {
        throw new RuntimeException('An exhausted cross-session login quota must block the request.');
    }
}

echo 'Browser mutation boundary tests passed.' . PHP_EOL;
