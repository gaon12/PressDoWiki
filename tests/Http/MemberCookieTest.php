<?php

declare(strict_types=1);

use PressDo\App\Core\Model;
use PressDo\App\Helpers\Database;
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
$pdo->exec('CREATE TABLE cookies (user BLOB, name TEXT, value BLOB, expiry INTEGER)');
(new ReflectionProperty(Model::class, 'db'))->setValue(null, $pdo);
(new ReflectionProperty(Database::class, 'driver'))->setValue(null, 'sqlite');

$owner = '00000000-0000-4000-8000-000000000001';
$otherUser = '00000000-0000-4000-8000-000000000002';
$trust = Member::saveCookies($owner, 'podgorica', 600);
$expired = Member::saveCookies($owner, 'szczecin', -1);
$remember = Member::saveCookies($owner, 'szczecin', 600);

assertMemberCookie($owner, Member::checkCookie('podgorica', $trust, $owner), 'Trust should apply to its owner.');
assertMemberCookie(null, Member::checkCookie('podgorica', $trust, $otherUser), 'Trust must not apply to another user.');
assertMemberCookie(null, Member::checkCookie('szczecin', $trust), 'Trust cannot be used for automatic login.');
assertMemberCookie(null, Member::checkCookie('szczecin', $expired), 'Expired cookies must not be accepted.');
assertMemberCookie(null, Member::checkCookie('szczecin', 'bad-input'), 'Malformed cookie values must be rejected.');
assertMemberCookie($owner, Member::checkCookie('szczecin', $remember), 'A valid remember-me cookie should resolve.');
Member::deleteCookie($otherUser, 'szczecin', $remember);
assertMemberCookie($owner, Member::checkCookie('szczecin', $remember), 'Only the owner may revoke their cookie.');
Member::deleteCookie($owner, 'szczecin', $remember);
assertMemberCookie(null, Member::checkCookie('szczecin', $remember), 'Revoked cookies must not be accepted.');

echo 'Member cookie tests passed.' . PHP_EOL;
