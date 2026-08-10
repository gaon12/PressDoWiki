<?php

declare(strict_types=1);

use PressDo\App\Core\Model;
use PressDo\App\Models\Files;
use PressDo\App\Services\Document\DocumentRevision;
use PressDo\App\Services\File\FileMetadata;
use PressDo\App\Services\File\PdoFileDocumentStore;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failFileDocumentStoreTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDOUT, 'File document transaction tests skipped: pdo_sqlite is unavailable.' . PHP_EOL);
    exit(0);
}

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

/** @return string A validated binary fixture identifier. */
function fileDocumentTestId(string $hex, int $bytes): string
{
    $id = hex2bin($hex);
    if ($id === false || strlen($id) !== $bytes) {
        failFileDocumentStoreTest("File fixture must contain exactly {$bytes} bytes.");
    }

    return $id;
}

function fileDocumentRevision(
    string $documentId,
    string $revisionHex,
    string $comment = 'uploaded',
): DocumentRevision {
    return new DocumentRevision(
        revisionId: fileDocumentTestId($revisionHex, 16),
        documentId: $documentId,
        content: '파일 설명 본문',
        comment: $comment,
        action: 'create',
        revision: 1,
        lengthDelta: 8,
    );
}

$store = new PdoFileDocumentStore($database);
$documentId = fileDocumentTestId('00112233445566778899aabbccddeeff', 16);
$digest = fileDocumentTestId(str_repeat('ab', 32), 32);
$store->create(
    '파일',
    '예시.webp',
    fileDocumentRevision($documentId, '11112222333344445555666677778888'),
    new FileMetadata($documentId, $digest, 640, 480),
);

$joined = $database->query(
    'SELECT d.namespace, d.title, h.content, h.rev, f.hash, f.width, f.height FROM document d JOIN history h ON h.document=d.uuid JOIN files f ON f.uuid=d.uuid',
)->fetch(PDO::FETCH_ASSOC);
if (
    !is_array($joined)
    || $joined['namespace'] !== '파일'
    || $joined['title'] !== '예시.webp'
    || $joined['content'] !== '파일 설명 본문'
    || $joined['rev'] !== 1
    || $joined['hash'] !== $digest
    || $joined['width'] !== 640
    || $joined['height'] !== 480
) {
    failFileDocumentStoreTest('A file document should persist its page, initial revision, and metadata together.');
}

$database->exec(<<<'SQL'
    CREATE TRIGGER reject_file_metadata
    BEFORE INSERT ON files
    WHEN NEW.width = 13
    BEGIN
        SELECT RAISE(ABORT, 'file metadata rejected');
    END
    SQL);
$rejectedId = fileDocumentTestId('22223333444455556666777788889999', 16);
try {
    $store->create(
        '파일',
        '메타데이터 실패.webp',
        fileDocumentRevision($rejectedId, '3333444455556666777788889999aaaa'),
        new FileMetadata($rejectedId, fileDocumentTestId(str_repeat('bc', 32), 32), 13, 20),
    );
    failFileDocumentStoreTest('Rejected file metadata should abort the document transaction.');
} catch (PDOException) {
}
$rejectedDocument = $database->prepare('SELECT COUNT(*) FROM document WHERE uuid=?');
$rejectedDocument->execute([$rejectedId]);
if ((int) $rejectedDocument->fetchColumn() !== 0) {
    failFileDocumentStoreTest('A metadata failure must not leave a document row.');
}
$rejectedHistory = $database->prepare('SELECT COUNT(*) FROM history WHERE document=?');
$rejectedHistory->execute([$rejectedId]);
if ((int) $rejectedHistory->fetchColumn() !== 0) {
    failFileDocumentStoreTest('A metadata failure must roll back the initial revision.');
}
$database->exec('DROP TRIGGER reject_file_metadata');

$duplicateId = fileDocumentTestId('444455556666777788889999aaaabbbb', 16);
try {
    $store->create(
        '파일',
        '중복 해시.webp',
        fileDocumentRevision($duplicateId, '55556666777788889999aaaabbbbcccc'),
        new FileMetadata($duplicateId, $digest, 320, 240),
    );
    failFileDocumentStoreTest('A duplicate binary hash should be rejected.');
} catch (PDOException) {
}
$duplicateDocument = $database->prepare('SELECT COUNT(*) FROM document WHERE uuid=?');
$duplicateDocument->execute([$duplicateId]);
if ((int) $duplicateDocument->fetchColumn() !== 0) {
    failFileDocumentStoreTest('A duplicate hash must roll back its new document and revision.');
}

$outerId = fileDocumentTestId('6666777788889999aaaabbbbccccdddd', 16);
$database->beginTransaction();
$store->create(
    '파일',
    '외부 트랜잭션.webp',
    fileDocumentRevision($outerId, '777788889999aaaabbbbccccddddeeee'),
    new FileMetadata($outerId, fileDocumentTestId(str_repeat('cd', 32), 32), 10, 20),
);
if (!$database->inTransaction()) {
    failFileDocumentStoreTest('The file store must not commit a caller-owned transaction.');
}
$database->rollBack();
$outerDocument = $database->prepare('SELECT COUNT(*) FROM document WHERE uuid=?');
$outerDocument->execute([$outerId]);
if ((int) $outerDocument->fetchColumn() !== 0) {
    failFileDocumentStoreTest('The caller should retain control over rolling back a file document.');
}

(new ReflectionProperty(Model::class, 'db'))->setValue(null, $database);
$modelHash = str_repeat('de', 32);
$modelUuid = Files::createDocument(
    '파일',
    '모델.webp',
    '모델 파일 본문',
    'model upload',
    null,
    null,
    $modelHash,
    800,
    600,
);
$modelMetadata = $database->prepare('SELECT hash, width, height FROM files WHERE uuid=?');
$modelMetadata->execute([Files::uuid2bin($modelUuid)]);
$modelRow = $modelMetadata->fetch(PDO::FETCH_ASSOC);
if (
    !is_array($modelRow)
    || bin2hex($modelRow['hash']) !== $modelHash
    || $modelRow['width'] !== 800
    || $modelRow['height'] !== 600
) {
    failFileDocumentStoreTest('Files::createDocument should use the atomic file document store.');
}

echo 'File document transaction tests passed.' . PHP_EOL;
