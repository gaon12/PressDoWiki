<?php

declare(strict_types=1);

use PressDo\App\Core\Model;
use PressDo\App\Helpers\Database;
use PressDo\App\Http\Security\EmailVerification;
use PressDo\App\Http\Security\EmailVerificationPurpose as Purpose;
use PressDo\App\Models\Member;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo 'Email verification tests skipped (pdo_sqlite unavailable).' . PHP_EOL;
    exit(0);
}

function expectEmailVerification(bool $passed, string $failure): void
{
    if (!$passed) {
        throw new RuntimeException($failure);
    }
}

$database = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec(file_get_contents(dirname(__DIR__, 2) . '/templates/database_scheme.sqlite.sql'));
(new ReflectionProperty(Model::class, 'db'))->setValue(null, $database);
(new ReflectionProperty(Database::class, 'driver'))->setValue(null, 'sqlite');

$service = new EmailVerification($database);
$ip = '192.0.2.10';
$email = 'signup@example.test';
$signup = Member::issueEmailCode($email, $ip, Purpose::Signup);
expectEmailVerification(is_string($signup) && strlen($signup) === 64, 'A signup must have a fresh email token.');
expectEmailVerification(
    bin2hex($database->query('SELECT key FROM email_keys')->fetchColumn()) !== $signup,
    'The token itself must not be stored.',
);
expectEmailVerification($service->verify($signup, Purpose::Signup, $ip) === $email, 'The right signup token should verify.');
expectEmailVerification($service->verify($signup, Purpose::Signup, '192.0.2.11') === null, 'Signup must preserve its IP requirement.');
expectEmailVerification($service->verify($signup, Purpose::PasswordReset) === null, 'Signup verification must not authorize a reset.');
expectEmailVerification($service->issue($email, $ip, Purpose::Signup) === null, 'Recent issuance should prevent another email immediately.');

expectEmailVerification(
    !$service->consume($signup, Purpose::Signup, static fn(string $email): bool => false, $ip)
        && $service->verify($signup, Purpose::Signup, $ip) === $email,
    'A failed account operation must roll back token consumption.',
);
expectEmailVerification(Member::completeSignup($signup, $ip, 'first_member', 'test-password!'), 'A verified signup should create the account.');
expectEmailVerification(!Member::completeSignup($signup, $ip, 'second_member', 'test-password!'), 'A consumed token must not register again.');
expectEmailVerification((int) $database->query('SELECT COUNT(*) FROM document')->fetchColumn() === 1, 'Signup should create one user document.');

$member = Member::exist(email: $email);
expectEmailVerification(is_array($member), 'The registered account should be found.');
$remember = Member::saveCookies($member['uuid'], 'szczecin', 600);
$trusted = Member::saveCookies($member['uuid'], 'podgorica', 600);
$reset = Member::issueEmailCode($email, $ip, Purpose::PasswordReset);
expectEmailVerification(is_string($reset), 'A reset request should issue a token.');
expectEmailVerification($service->verify($reset, Purpose::Signup, $ip) === null, 'A reset must never authorize a signup.');
expectEmailVerification(Member::completePasswordReset($reset, 'new-password!'), 'A current reset token should change the password.');
expectEmailVerification(!Member::completePasswordReset($reset, 'replay-password!'), 'A password reset token may only work once.');
expectEmailVerification(Member::checkMember($email, 'test-password!') === false, 'The old password must no longer work.');
expectEmailVerification(Member::checkMember($email, 'new-password!') !== false, 'The new password should authenticate.');
expectEmailVerification(
    Member::checkCookie('szczecin', $remember) === null && Member::checkCookie('podgorica', $trusted) === null,
    'Password reset must revoke remember-me and trusted-device tokens.',
);

$expired = Member::issueEmailCode($email, $ip, Purpose::PasswordReset);
expectEmailVerification(is_string($expired), 'A second reset should receive a new token.');
$database->prepare('UPDATE email_keys SET time = ?')->execute([time() - 1_801]);
expectEmailVerification(!Member::completePasswordReset($expired, 'expired-password!'), 'An expired reset must not change the password.');

echo 'Email verification tests passed.' . PHP_EOL;
