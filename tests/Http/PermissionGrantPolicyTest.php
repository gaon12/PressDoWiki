<?php

declare(strict_types=1);

use PressDo\App\Core\Model;
use PressDo\App\Helpers\Database;
use PressDo\App\Http\Security\PermissionGrantPolicy;
use PressDo\App\Models\Member;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectGrant(bool $passed, string $failure): void
{
    if (!$passed) {
        throw new RuntimeException($failure);
    }
}

$catalog = ['admin', 'developer', 'grant', 'hideip', 'mark_troll_revision', 'delete_thread'];
$delegated = ['grant', 'mark_troll_revision'];
expectGrant(
    PermissionGrantPolicy::manageable($delegated, [], $catalog, false) === ['mark_troll_revision'],
    'A delegate can only grant an unprotected permission they hold.',
);
expectGrant(
    PermissionGrantPolicy::manageable($delegated, ['developer'], $catalog, false) === [],
    'A delegate may not modify a protected account.',
);
expectGrant(
    PermissionGrantPolicy::manageable(['developer'], [], $catalog, true) === [],
    'Granting permissions to the acting account must be blocked.',
);
expectGrant(
    PermissionGrantPolicy::manageable(['developer'], [], $catalog, false) === $catalog,
    'A developer can administer the permission catalog.',
);

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $database = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $database->exec('CREATE TABLE member (uuid BLOB PRIMARY KEY, perm TEXT)');
    $database->exec('CREATE TABLE BlockHistory (executor_m BLOB, target_member BLOB, action TEXT, granted TEXT)');
    (new ReflectionProperty(Model::class, 'db'))->setValue(null, $database);
    (new ReflectionProperty(Database::class, 'driver'))->setValue(null, 'sqlite');

    $actor = '00000000-0000-4000-8000-000000000001';
    $target = '00000000-0000-4000-8000-000000000002';
    $database->prepare('INSERT INTO member (uuid, perm) VALUES (?, NULL)')->execute([Model::uuid2bin($target)]);
    expectGrant(Member::grantPermissions($actor, $target, '', ['mark_troll_revision'], '+mark_troll_revision'), 'Permission update should succeed.');
    expectGrant(!Member::grantPermissions($actor, $target, '', [], '-mark_troll_revision'), 'A stale update must be rejected.');
    expectGrant($database->query('SELECT COUNT(*) FROM BlockHistory')->fetchColumn() === 1, 'Only the successful change should be audited.');

    $database->exec('DROP TABLE BlockHistory');
    try {
        Member::grantPermissions($actor, $target, 'mark_troll_revision', [], '-mark_troll_revision');
        throw new RuntimeException('An audit write failure should fail the grant.');
    } catch (PDOException) {
        expectGrant(Member::specialPerms($target) === ['mark_troll_revision'], 'An audit failure must roll back the permission update.');
    }
}

echo 'Permission grant policy tests passed.' . PHP_EOL;
