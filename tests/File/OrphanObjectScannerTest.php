<?php

declare(strict_types=1);

use PressDo\App\Services\File\FileObjectLocator;
use PressDo\App\Services\File\OrphanObjectScanner;
use PressDo\App\Services\File\PdoObjectReferenceRepository;
use PressDo\App\Services\Uploaders\ObjectInfo;
use PressDo\App\Services\Uploaders\ObjectKey;
use PressDo\App\Services\Uploaders\ObjectPage;
use PressDo\App\Services\Uploaders\ObjectStorageInterface;
use PressDo\App\Services\Uploaders\StoredObject;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failOrphanObjectScannerTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDOUT, 'Orphan object scanner tests skipped: pdo_sqlite is unavailable.' . PHP_EOL);
    exit(0);
}

final readonly class InventoryStorage implements ObjectStorageInterface
{
    /** @param list<ObjectInfo> $items */
    public function __construct(private array $items, private ?ObjectKey $nextCursor) {}

    public function store(ObjectKey $key, string $sourcePath): StoredObject
    {
        return new StoredObject($key, false);
    }

    public function exists(ObjectKey $key): bool
    {
        return false;
    }

    public function metadata(ObjectKey $key): ?ObjectInfo
    {
        return null;
    }

    public function listObjects(?ObjectKey $after, int $limit): ObjectPage
    {
        return new ObjectPage(array_slice($this->items, 0, $limit), $this->nextCursor);
    }

    public function delete(ObjectKey $key): void {}
}

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec('CREATE TABLE document (uuid BLOB PRIMARY KEY, title TEXT NOT NULL)');
$database->exec('CREATE TABLE files (uuid BLOB NOT NULL, hash BLOB NOT NULL UNIQUE)');
$documentId = hex2bin(str_repeat('01', 16));
$digest = hex2bin(str_repeat('ab', 32));
if ($documentId === false || $digest === false) {
    failOrphanObjectScannerTest('Orphan scanner fixtures must be valid hexadecimal values.');
}
$insertDocument = $database->prepare('INSERT INTO document (uuid, title) VALUES (?, ?)');
$insertDocument->execute([$documentId, '참조.png']);
$insertFile = $database->prepare('INSERT INTO files (uuid, hash) VALUES (?, ?)');
$insertFile->execute([$documentId, $digest]);

$now = 1_800_000_000;
$current = FileObjectLocator::keyForDocument(str_repeat('ab', 32), '참조.png');
$legacy = FileObjectLocator::keyForExtension(str_repeat('ab', 32), 'webp');
$oldOrphan = FileObjectLocator::keyForExtension(str_repeat('cd', 32), 'gif');
$recentOrphan = FileObjectLocator::keyForExtension(str_repeat('ef', 32), 'png');
$unmanaged = new ObjectKey('misc/readme.txt');
$cursor = new ObjectKey('ff/' . str_repeat('f', 64) . '.webp');
$storage = new InventoryStorage([
    new ObjectInfo($current, $now - 200_000, 10),
    new ObjectInfo($legacy, $now - 200_000, 11),
    new ObjectInfo($oldOrphan, $now - 90_000, 12),
    new ObjectInfo($recentOrphan, $now - 60, 13),
    new ObjectInfo($unmanaged, $now - 200_000, 14),
], $cursor);

$report = (new OrphanObjectScanner(
    $storage,
    new PdoObjectReferenceRepository($database),
))->scan(null, 10, $now, 86_400);

if (
    $report->scanned !== 5
    || count($report->candidates) !== 1
    || $report->candidates[0]->object->key->value !== $oldOrphan->value
    || $report->ignoredRecent !== 1
    || $report->ignoredUnmanaged !== 1
    || $report->nextCursor?->value !== $cursor->value
) {
    failOrphanObjectScannerTest('The scanner should report only old, managed, unreferenced objects.');
}

echo 'Orphan object scanner tests passed.' . PHP_EOL;
