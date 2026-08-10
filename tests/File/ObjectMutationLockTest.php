<?php

declare(strict_types=1);

use PressDo\App\Services\File\PdoObjectMutationLock;
use PressDo\App\Services\Uploaders\ObjectKey;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failObjectMutationLockTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDOUT, 'Object mutation lock tests skipped: pdo_sqlite is unavailable.' . PHP_EOL);
    exit(0);
}

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec('CREATE TABLE mutation (value TEXT NOT NULL)');
$lock = new PdoObjectMutationLock($database);
$key = new ObjectKey('aa/' . str_repeat('a', 64) . '.png');

$returned = $lock->synchronized($key, static function () use ($database): string {
    $database->exec("INSERT INTO mutation (value) VALUES ('committed')");

    return 'result';
});
if ($returned !== 'result' || $database->query('SELECT COUNT(*) FROM mutation')->fetchColumn() !== 1) {
    failObjectMutationLockTest('A successful locked operation should return its value and commit once.');
}

try {
    $lock->synchronized($key, static function () use ($database): never {
        $database->exec("INSERT INTO mutation (value) VALUES ('rolled-back')");
        throw new RuntimeException('expected failure');
    });
    failObjectMutationLockTest('A failing locked operation should rethrow its exception.');
} catch (RuntimeException $error) {
    if ($error->getMessage() !== 'expected failure') {
        throw $error;
    }
}
if ($database->query('SELECT COUNT(*) FROM mutation')->fetchColumn() !== 1) {
    failObjectMutationLockTest('A failing locked operation should roll back its database changes.');
}

$database->beginTransaction();
try {
    $lock->synchronized($key, static fn(): null => null);
    failObjectMutationLockTest('SQLite should reject a lock nested inside an existing transaction.');
} catch (RuntimeException $error) {
    if (!str_contains($error->getMessage(), 'outer transaction')) {
        throw $error;
    }
} finally {
    $database->rollBack();
}

echo 'Object mutation lock tests passed.' . PHP_EOL;
