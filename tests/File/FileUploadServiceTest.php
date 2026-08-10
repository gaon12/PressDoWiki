<?php

declare(strict_types=1);

use PressDo\App\Services\Document\DocumentRevision;
use PressDo\App\Services\File\DuplicateFileException;
use PressDo\App\Services\File\FileMetadata;
use PressDo\App\Services\File\FileUploadCompensationException;
use PressDo\App\Services\File\FileUploadService;
use PressDo\App\Services\File\PdoFileDocumentStore;
use PressDo\App\Services\File\PendingFileUpload;
use PressDo\App\Services\Uploaders\ObjectKey;
use PressDo\App\Services\Uploaders\ObjectPage;
use PressDo\App\Services\Uploaders\ObjectStorageInterface;
use PressDo\App\Services\Uploaders\StorageException;
use PressDo\App\Services\Uploaders\StoredObject;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failFileUploadServiceTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDOUT, 'File upload compensation tests skipped: pdo_sqlite is unavailable.' . PHP_EOL);
    exit(0);
}

final class RecordingObjectStorage implements ObjectStorageInterface
{
    /** @var list<string> */
    public array $stored = [];

    /** @var list<string> */
    public array $deleted = [];

    /** @param null|Closure(ObjectKey): void $onStore */
    public function __construct(
        private readonly bool $createsObject,
        private readonly bool $failDelete = false,
        private readonly ?Closure $onStore = null,
    ) {}

    public function store(ObjectKey $key, string $sourcePath): StoredObject
    {
        $this->stored[] = $key->value;
        if ($this->onStore !== null) {
            ($this->onStore)($key);
        }

        return new StoredObject($key, $this->createsObject);
    }

    public function exists(ObjectKey $key): bool
    {
        return false;
    }

    public function listObjects(?ObjectKey $after, int $limit): ObjectPage
    {
        return new ObjectPage([], null);
    }

    public function delete(ObjectKey $key): void
    {
        $this->deleted[] = $key->value;
        if ($this->failDelete) {
            throw new StorageException('simulated compensation failure');
        }
    }
}

function uploadServiceDatabase(): PDO
{
    $database = new PDO('sqlite::memory:');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->exec('PRAGMA foreign_keys=ON');
    $database->exec(
        "CREATE TABLE document (uuid BLOB PRIMARY KEY, namespace TEXT NOT NULL, title TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'normal', backlink_updated INTEGER NOT NULL DEFAULT 0, UNIQUE (namespace, title))",
    );
    $database->exec(
        'CREATE TABLE history (uuid BLOB PRIMARY KEY, document BLOB NOT NULL, content TEXT, comment TEXT NOT NULL, action TEXT NOT NULL, rev INTEGER NOT NULL, count INTEGER NOT NULL, contributor_m BLOB, contributor_i BLOB, edit_request_uri TEXT, moved_from TEXT, moved_to TEXT, FOREIGN KEY (document) REFERENCES document(uuid))',
    );
    $database->exec(
        'CREATE TABLE files (uuid BLOB NOT NULL, hash BLOB NOT NULL UNIQUE, width INTEGER NOT NULL, height INTEGER NOT NULL, FOREIGN KEY (uuid) REFERENCES document(uuid))',
    );

    return $database;
}

function uploadServiceBinary(string $hex, int $bytes): string
{
    $binary = hex2bin($hex);
    if ($binary === false || strlen($binary) !== $bytes) {
        failFileUploadServiceTest("Upload fixture must contain {$bytes} bytes.");
    }

    return $binary;
}

function pendingUpload(string $documentHex, string $revisionHex, string $digestHex, string $title): PendingFileUpload
{
    $documentId = uploadServiceBinary($documentHex, 16);

    return new PendingFileUpload(
        namespace: '파일',
        title: $title,
        sourcePath: __FILE__,
        objectKey: new ObjectKey(substr($digestHex, 0, 2) . '/' . $digestHex . '.webp'),
        revision: new DocumentRevision(
            revisionId: uploadServiceBinary($revisionHex, 16),
            documentId: $documentId,
            content: '파일 설명',
            comment: 'uploaded',
            action: 'create',
            revision: 1,
            lengthDelta: 5,
        ),
        metadata: new FileMetadata($documentId, uploadServiceBinary($digestHex, 32), 100, 80),
    );
}

$database = uploadServiceDatabase();
$documents = new PdoFileDocumentStore($database);
$successfulStorage = new RecordingObjectStorage(true);
$successfulUpload = pendingUpload(
    '00112233445566778899aabbccddeeff',
    '11112222333344445555666677778888',
    str_repeat('ab', 32),
    '성공.webp',
);
(new FileUploadService($successfulStorage, $documents))->upload($successfulUpload);
if (count($successfulStorage->stored) !== 1 || $successfulStorage->deleted !== []) {
    failFileUploadServiceTest('A successful upload should store once without compensation.');
}

$database->exec("INSERT INTO document (uuid, namespace, title) VALUES (X'22223333444455556666777788889999', '파일', '제목 충돌.webp')");
$cleanupStorage = new RecordingObjectStorage(true);
$conflictingUpload = pendingUpload(
    '3333444455556666777788889999aaaa',
    '444455556666777788889999aaaabbbb',
    str_repeat('bc', 32),
    '제목 충돌.webp',
);
try {
    (new FileUploadService($cleanupStorage, $documents))->upload($conflictingUpload);
    failFileUploadServiceTest('A database title conflict should fail the upload.');
} catch (PressDo\App\Services\Document\DocumentConflictException) {
}
if ($cleanupStorage->deleted !== [$conflictingUpload->objectKey->value]) {
    failFileUploadServiceTest('A newly created object should be deleted after an unrelated DB failure.');
}

$duplicateStorage = new RecordingObjectStorage(false);
try {
    (new FileUploadService($duplicateStorage, $documents))->upload(pendingUpload(
        '55556666777788889999aaaabbbbcccc',
        '6666777788889999aaaabbbbccccdddd',
        str_repeat('ab', 32),
        '사전 중복.webp',
    ));
    failFileUploadServiceTest('A known duplicate digest should fail before object storage.');
} catch (DuplicateFileException) {
}
if ($duplicateStorage->stored !== []) {
    failFileUploadServiceTest('A known duplicate digest must not invoke object storage.');
}

$raceDatabase = uploadServiceDatabase();
$raceDocuments = new PdoFileDocumentStore($raceDatabase);
$raceDigestHex = str_repeat('cd', 32);
$raceOwnerId = uploadServiceBinary('777788889999aaaabbbbccccddddeeee', 16);
$raceStorage = new RecordingObjectStorage(true, onStore: static function () use (
    $raceDatabase,
    $raceOwnerId,
    $raceDigestHex,
): void {
    $insertDocument = $raceDatabase->prepare(
        "INSERT INTO document (uuid, namespace, title) VALUES (?, '파일', '경쟁 승자.webp')",
    );
    $insertDocument->execute([$raceOwnerId]);
    $insertFile = $raceDatabase->prepare('INSERT INTO files (uuid, hash, width, height) VALUES (?, ?, 1, 1)');
    $insertFile->execute([$raceOwnerId, uploadServiceBinary($raceDigestHex, 32)]);
});
try {
    (new FileUploadService($raceStorage, $raceDocuments))->upload(pendingUpload(
        '88889999aaaabbbbccccddddeeeeffff',
        '9999aaaabbbbccccddddeeeeffff0000',
        $raceDigestHex,
        '경쟁 패자.webp',
    ));
    failFileUploadServiceTest('A digest committed during object storage should reject the losing DB write.');
} catch (DuplicateFileException) {
}
if ($raceStorage->deleted !== []) {
    failFileUploadServiceTest('Compensation must preserve an object now referenced by the winning request.');
}

$compensationStorage = new RecordingObjectStorage(true, true);
try {
    (new FileUploadService($compensationStorage, $documents))->upload(pendingUpload(
        'aaaabbbbccccddddeeeeffff00001111',
        'bbbbccccddddeeeeffff000011112222',
        str_repeat('ef', 32),
        '제목 충돌.webp',
    ));
    failFileUploadServiceTest('A failed compensation should report both failures.');
} catch (FileUploadCompensationException $error) {
    if (!$error->compensationFailure instanceof StorageException) {
        failFileUploadServiceTest('The compensation exception should retain the cleanup failure.');
    }
}

echo 'File upload compensation tests passed.' . PHP_EOL;
