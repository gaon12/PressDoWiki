<?php

declare(strict_types=1);

use PressDo\App\Core\Model;
use PressDo\App\Helpers\Database;
use PressDo\App\Http\Security\SessionLifecycle;
use PressDo\App\Infrastructure\Database\SchemaMigrator;
use PressDo\App\Models\Member;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo 'Member cookie tests skipped (pdo_sqlite unavailable).' . PHP_EOL;
    exit(0);
}

function assertMemberCookie(?string $expected, ?string $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(file_get_contents(dirname(__DIR__, 2) . '/templates/database_scheme.sqlite.sql'));
(new SchemaMigrator($pdo))->migrate();
(new ReflectionProperty(Model::class, 'db'))->setValue(null, $pdo);
(new ReflectionProperty(Database::class, 'driver'))->setValue(null, 'sqlite');

$owner = '00000000-0000-4000-8000-000000000001';
$otherUser = '00000000-0000-4000-8000-000000000002';
$hash = password_hash('test-password', PASSWORD_BCRYPT, ['cost' => 4]);
$member = $pdo->prepare('INSERT INTO member (uuid, username, email, password) VALUES (?, ?, ?, ?)');
$member->execute([Model::uuid2bin($owner), 'owner', 'owner@example.test', $hash]);
$credential = SessionLifecycle::credential($hash);
$login = Member::login($owner, $credential, '192.0.2.1', 'fixture', ['szczecin' => 600, 'podgorica' => 600]);
$trust = $login['cookies']['podgorica'];
$remember = $login['cookies']['szczecin'];
$expired = Member::login($owner, $credential, '192.0.2.1', 'fixture', ['szczecin' => -1])['cookies']['szczecin'];

assertMemberCookie($owner, Member::checkCookie('podgorica', $trust, $owner), 'Trust should apply to its owner.');
assertMemberCookie(null, Member::checkCookie('podgorica', $trust, $otherUser), 'Trust must not apply to another user.');
assertMemberCookie(null, Member::checkCookie('szczecin', $trust), 'Trust cannot be used for automatic login.');
assertMemberCookie(null, Member::checkCookie('szczecin', $expired), 'Expired cookies must not be accepted.');
assertMemberCookie(null, Member::checkCookie('szczecin', 'bad-input'), 'Malformed cookie values must be rejected.');
assertMemberCookie($owner, Member::checkCookie('szczecin', $remember), 'A valid remember-me cookie should resolve.');
$restored = Member::login($owner, $credential, '192.0.2.1', 'fixture', rememberToken: $remember);
assertMemberCookie($login['device_session'], $restored['device_session'], 'Restoring a login should retain its existing registry entry.');
Member::revokeLoginSession($otherUser, $login['device_session']);
assertMemberCookie($owner, Member::checkCookie('szczecin', $remember), 'Only the owner may revoke their cookie.');
Member::revokeLoginSession($owner, $login['device_session']);
assertMemberCookie(null, Member::checkCookie('szczecin', $remember), 'Revoked cookies must not be accepted.');
assertMemberCookie(null, Member::checkCookie('podgorica', $trust), 'Revocation must also remove trust for the browser.');
assertMemberCookie(null, Member::login($owner, $credential, '192.0.2.1', 'fixture', rememberToken: $remember)['device_session'] ?? null, 'Previously captured credentials must not restore a revoked device.');
assertMemberCookie(null, Member::login($owner, $credential, '192.0.2.1', 'fixture', trustedToken: $trust)['device_session'] ?? null, 'Revoked trust must be checked again before login completes.');

echo 'Member cookie tests passed.' . PHP_EOL;
