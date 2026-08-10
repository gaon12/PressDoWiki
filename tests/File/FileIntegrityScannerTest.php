<?php

declare(strict_types=1);

use PressDo\App\Services\File\FileIntegrityScanner;
use PressDo\App\Services\File\FileObjectLocator;
use PressDo\App\Services\File\FileObjectResolver;
use PressDo\App\Services\File\FileObjectState;
use PressDo\App\Services\File\PdoFileIntegrityRepository;
use PressDo\App\Services\Uploaders\ObjectInfo;
use PressDo\App\Services\Uploaders\ObjectKey;
use PressDo\App\Services\Uploaders\ObjectPage;
use PressDo\App\Services\Uploaders\ObjectStorageInterface;
use PressDo\App\Services\Uploaders\StoredObject;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failFileIntegrityScannerTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDOUT, 'File integrity scanner tests skipped: pdo_sqlite is unavailable.' . PHP_EOL);
    exit(0);
}

final readonly class IntegrityObjectStorage implements ObjectStorageInterface
{
    /** @param list<string> $existing */
    public function __construct(private array $existing) {}

    public function store(ObjectKey $key, string $sourcePath): StoredObject
    {
        return new StoredObject($key, false);
    }

    public function exists(ObjectKey $key): bool
    {
        return in_array($key->value, $this->existing, true);
    }

    public function metadata(ObjectKey $key): ?ObjectInfo
    {
        return null;
    }

    public function listObjects(?ObjectKey $after, int $limit): ObjectPage
    {
        return new ObjectPage([], null);
    }

    public function delete(ObjectKey $key): void {}
}

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec('CREATE TABLE document (uuid BLOB PRIMARY KEY, namespace TEXT NOT NULL, title TEXT NOT NULL)');
$database->exec('CREATE TABLE files (uuid BLOB NOT NULL, hash BLOB NOT NULL UNIQUE, width INTEGER, height INTEGER)');

$fixtures = [
    [str_repeat('01', 16), str_repeat('a1', 32), '현재.png'],
    [str_repeat('02', 16), str_repeat('b2', 32), '레거시.jpg'],
    [str_repeat('03', 16), str_repeat('c3', 32), '누락.gif'],
];
$insertDocument = $database->prepare("INSERT INTO document (uuid, namespace, title) VALUES (?, '파일', ?)");
$insertFile = $database->prepare('INSERT INTO files (uuid, hash, width, height) VALUES (?, ?, 1, 1)');
foreach ($fixtures as [$documentHex, $digestHex, $title]) {
    $documentId = hex2bin($documentHex);
    $digest = hex2bin($digestHex);
    if ($documentId === false || $digest === false) {
        failFileIntegrityScannerTest('Integrity test fixtures must be valid hexadecimal values.');
    }
    $insertDocument->execute([$documentId, $title]);
    $insertFile->execute([$documentId, $digest]);
}

$currentKey = FileObjectLocator::keyForDocument($fixtures[0][1], $fixtures[0][2])->value;
$legacyKey = FileObjectLocator::keyForExtension($fixtures[1][1], 'webp')->value;
$storage = new IntegrityObjectStorage([$currentKey, $legacyKey]);
$scanner = new FileIntegrityScanner(
    new PdoFileIntegrityRepository($database),
    new FileObjectResolver($storage),
);
$report = $scanner->scan(0, 100);

if ($report->total !== 3 || count($report->items) !== 3) {
    failFileIntegrityScannerTest('The integrity report should include every stored file in the requested page.');
}
$states = array_map(static fn($item): FileObjectState => $item->object->state, $report->items);
if ($states !== [FileObjectState::Current, FileObjectState::Legacy, FileObjectState::Missing]) {
    failFileIntegrityScannerTest('The scanner should distinguish current, legacy, and missing objects.');
}
if ($report->items[1]->file->fullTitle() !== '파일:레거시.jpg') {
    failFileIntegrityScannerTest('Integrity rows should retain their full document title.');
}
$secondPage = $scanner->scan(1, 1);
if ($secondPage->total !== 3 || count($secondPage->items) !== 1 || $secondPage->items[0]->object->state !== FileObjectState::Legacy) {
    failFileIntegrityScannerTest('Integrity scans must honor bounded offset pagination.');
}

echo 'File integrity scanner tests passed.' . PHP_EOL;
