<?php

declare(strict_types=1);

use PressDo\App\Infrastructure\Files\AuditLoggerInterface;
use PressDo\App\Services\File\FileObjectLocator;
use PressDo\App\Services\File\OrphanObjectCleaner;
use PressDo\App\Services\File\OrphanObjectCleanupException;
use PressDo\App\Services\File\PdoObjectMutationLock;
use PressDo\App\Services\File\PdoObjectReferenceRepository;
use PressDo\App\Services\Uploaders\ObjectInfo;
use PressDo\App\Services\Uploaders\ObjectKey;
use PressDo\App\Services\Uploaders\ObjectPage;
use PressDo\App\Services\Uploaders\ObjectStorageInterface;
use PressDo\App\Services\Uploaders\StorageException;
use PressDo\App\Services\Uploaders\StoredObject;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failOrphanObjectCleanerTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDOUT, 'Orphan object cleaner tests skipped: pdo_sqlite is unavailable.' . PHP_EOL);
    exit(0);
}

final class CleanupStorage implements ObjectStorageInterface
{
    /** @var list<string> */
    public array $deleted = [];

    public function __construct(public ?ObjectInfo $object, private readonly bool $failDelete = false) {}

    public function store(ObjectKey $key, string $sourcePath): StoredObject
    {
        return new StoredObject($key, false);
    }

    public function exists(ObjectKey $key): bool
    {
        return $this->object?->key->value === $key->value;
    }

    public function metadata(ObjectKey $key): ?ObjectInfo
    {
        return $this->exists($key) ? $this->object : null;
    }

    public function listObjects(?ObjectKey $after, int $limit): ObjectPage
    {
        return new ObjectPage([], null);
    }

    public function delete(ObjectKey $key): void
    {
        if ($this->failDelete) {
            throw new StorageException('simulated storage failure');
        }

        $this->deleted[] = $key->value;
        $this->object = null;
    }
}

final class CleanupAudit implements AuditLoggerInterface
{
    /** @var list<array{event: string, context: array<string, bool|float|int|string|null>}> */
    public array $records = [];

    public function __construct(private readonly bool $failFirst = false) {}

    public function record(string $event, array $context): void
    {
        if ($this->failFirst && $this->records === []) {
            throw new RuntimeException('simulated audit failure');
        }

        $this->records[] = ['event' => $event, 'context' => $context];
    }
}

function cleanupDatabase(): PDO
{
    $database = new PDO('sqlite::memory:');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->exec('CREATE TABLE document (uuid BLOB PRIMARY KEY, title TEXT NOT NULL)');
    $database->exec('CREATE TABLE files (uuid BLOB NOT NULL, hash BLOB NOT NULL UNIQUE)');

    return $database;
}

function cleaner(PDO $database, CleanupStorage $storage, CleanupAudit $audit): OrphanObjectCleaner
{
    return new OrphanObjectCleaner(
        $storage,
        new PdoObjectReferenceRepository($database),
        new PdoObjectMutationLock($database),
        $audit,
    );
}

$now = 1_800_000_000;
$oldModified = $now - 90_000;
$orphanKey = FileObjectLocator::keyForExtension(str_repeat('cd', 32), 'gif');
$database = cleanupDatabase();
$storage = new CleanupStorage(new ObjectInfo($orphanKey, $oldModified, 123));
$audit = new CleanupAudit();
$deleted = cleaner($database, $storage, $audit)->delete($orphanKey, $oldModified, $now, 'admin-uuid');
if (
    $deleted->key->value !== $orphanKey->value
    || $storage->deleted !== [$orphanKey->value]
    || array_column($audit->records, 'event') !== ['storage.orphan_delete_authorized', 'storage.orphan_deleted']
) {
    failOrphanObjectCleanerTest('An old unreferenced object should be audited before and after one deletion.');
}

$referencedKey = FileObjectLocator::keyForDocument(str_repeat('ab', 32), '참조.png');
$referencedDatabase = cleanupDatabase();
$documentId = hex2bin(str_repeat('01', 16));
$digest = hex2bin(str_repeat('ab', 32));
if ($documentId === false || $digest === false) {
    failOrphanObjectCleanerTest('Cleaner fixtures must contain valid hexadecimal values.');
}
$insertDocument = $referencedDatabase->prepare('INSERT INTO document (uuid, title) VALUES (?, ?)');
$insertDocument->execute([$documentId, '참조.png']);
$insertFile = $referencedDatabase->prepare('INSERT INTO files (uuid, hash) VALUES (?, ?)');
$insertFile->execute([$documentId, $digest]);
$referencedStorage = new CleanupStorage(new ObjectInfo($referencedKey, $oldModified, 1));
$referencedAudit = new CleanupAudit();
try {
    cleaner($referencedDatabase, $referencedStorage, $referencedAudit)
        ->delete($referencedKey, $oldModified, $now, 'admin-uuid');
    failOrphanObjectCleanerTest('A newly referenced object must not be deleted.');
} catch (OrphanObjectCleanupException) {
}
if ($referencedStorage->deleted !== [] || $referencedAudit->records !== []) {
    failOrphanObjectCleanerTest('Rejected referenced objects must not reach deletion or authorization audit.');
}

foreach (
    [
        'changed metadata' => [$oldModified + 1, $oldModified],
        'active grace period' => [$now - 60, $now - 60],
    ] as $case => [$actualModified, $expectedModified]
) {
    $caseDatabase = cleanupDatabase();
    $caseStorage = new CleanupStorage(new ObjectInfo($orphanKey, $actualModified, 1));
    try {
        cleaner($caseDatabase, $caseStorage, new CleanupAudit())
            ->delete($orphanKey, $expectedModified, $now, 'admin-uuid');
        failOrphanObjectCleanerTest("Cleanup should reject {$case}.");
    } catch (OrphanObjectCleanupException) {
    }
    if ($caseStorage->deleted !== []) {
        failOrphanObjectCleanerTest("Rejected {$case} must not delete storage bytes.");
    }
}

$auditFailureDatabase = cleanupDatabase();
$auditFailureStorage = new CleanupStorage(new ObjectInfo($orphanKey, $oldModified, 1));
try {
    cleaner($auditFailureDatabase, $auditFailureStorage, new CleanupAudit(true))
        ->delete($orphanKey, $oldModified, $now, 'admin-uuid');
    failOrphanObjectCleanerTest('Deletion must stop when its authorization audit cannot be persisted.');
} catch (RuntimeException) {
}
if ($auditFailureStorage->deleted !== []) {
    failOrphanObjectCleanerTest('An authorization audit failure must leave the object untouched.');
}

$deleteFailureDatabase = cleanupDatabase();
$deleteFailureStorage = new CleanupStorage(new ObjectInfo($orphanKey, $oldModified, 1), true);
$deleteFailureAudit = new CleanupAudit();
try {
    cleaner($deleteFailureDatabase, $deleteFailureStorage, $deleteFailureAudit)
        ->delete($orphanKey, $oldModified, $now, 'admin-uuid');
    failOrphanObjectCleanerTest('Storage deletion failures should be reported.');
} catch (OrphanObjectCleanupException) {
}
if (array_column($deleteFailureAudit->records, 'event') !== ['storage.orphan_delete_authorized', 'storage.orphan_delete_failed']) {
    failOrphanObjectCleanerTest('A storage failure should retain authorization and failure audit events.');
}

echo 'Orphan object cleaner tests passed.' . PHP_EOL;
