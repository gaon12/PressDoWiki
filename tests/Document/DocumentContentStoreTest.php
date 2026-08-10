<?php

declare(strict_types=1);

use PressDo\App\Core\Model;
use PressDo\App\Models\Document;
use PressDo\App\Services\Document\DocumentRevision;
use PressDo\App\Services\Document\PdoDocumentContentStore;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failDocumentContentStoreTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDOUT, 'Document content transaction tests skipped: pdo_sqlite is unavailable.' . PHP_EOL);
    exit(0);
}

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec(
    "CREATE TABLE document (uuid BLOB PRIMARY KEY, namespace TEXT NOT NULL, title TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'normal', backlink_updated INTEGER NOT NULL DEFAULT 0, UNIQUE (namespace, title))",
);
$database->exec(
    'CREATE TABLE history (uuid BLOB PRIMARY KEY, document BLOB NOT NULL, content TEXT, comment TEXT NOT NULL, action TEXT NOT NULL, rev INTEGER NOT NULL, count INTEGER NOT NULL, contributor_m BLOB, contributor_i BLOB, edit_request_uri TEXT, moved_from TEXT, moved_to TEXT)',
);

/** @return string A validated 16-byte test identifier. */
function contentTestId(string $hex): string
{
    $id = hex2bin($hex);
    if ($id === false || strlen($id) !== 16) {
        failDocumentContentStoreTest('Document content UUID fixtures must contain 32 hexadecimal characters.');
    }

    return $id;
}

function contentRevision(
    string $documentId,
    string $revisionHex,
    string $content,
    int $revision = 1,
    string $comment = 'created',
): DocumentRevision {
    return new DocumentRevision(
        revisionId: contentTestId($revisionHex),
        documentId: $documentId,
        content: $content,
        comment: $comment,
        action: 'create',
        revision: $revision,
        lengthDelta: mb_strlen($content, 'UTF-8'),
    );
}

$store = new PdoDocumentContentStore($database);
$createdId = contentTestId('00112233445566778899aabbccddeeff');
$store->create('문서', '새 문서', contentRevision(
    $createdId,
    '11112222333344445555666677778888',
    '첫 본문',
));

$created = $database->query(
    'SELECT d.namespace, d.title, d.status, h.content, h.action, h.rev FROM document d JOIN history h ON h.document=d.uuid',
)->fetch(PDO::FETCH_ASSOC);
if ($created !== [
    'namespace' => '문서',
    'title' => '새 문서',
    'status' => 'normal',
    'content' => '첫 본문',
    'action' => 'create',
    'rev' => 1,
]) {
    failDocumentContentStoreTest('Creating content should persist the document and initial revision together.');
}

$database->exec(<<<'SQL'
    CREATE TRIGGER reject_content_revision
    BEFORE INSERT ON history
    WHEN NEW.comment = 'reject'
    BEGIN
        SELECT RAISE(ABORT, 'content revision rejected');
    END
    SQL);

$rejectedCreateId = contentTestId('22223333444455556666777788889999');
try {
    $store->create('문서', '생성 롤백', contentRevision(
        $rejectedCreateId,
        '3333444455556666777788889999aaaa',
        '저장 실패 본문',
        comment: 'reject',
    ));
    failDocumentContentStoreTest('A failed initial revision should abort document creation.');
} catch (PDOException) {
}
$rejectedCreate = $database->prepare('SELECT COUNT(*) FROM document WHERE uuid=?');
$rejectedCreate->execute([$rejectedCreateId]);
if ((int) $rejectedCreate->fetchColumn() !== 0) {
    failDocumentContentStoreTest('A failed initial revision must not leave an empty document row.');
}

$recreateId = contentTestId('444455556666777788889999aaaabbbb');
$insertDeleted = $database->prepare(
    "INSERT INTO document (uuid, namespace, title, status, backlink_updated) VALUES (?, '문서', ?, 'delete', 1)",
);
$insertDeleted->execute([$recreateId, '삭제 문서']);
$store->recreate(contentRevision(
    $recreateId,
    '55556666777788889999aaaabbbbcccc',
    '복구 본문',
    revision: 3,
));
$recreated = $database->prepare('SELECT status, backlink_updated FROM document WHERE uuid=?');
$recreated->execute([$recreateId]);
if ($recreated->fetch(PDO::FETCH_ASSOC) !== ['status' => 'normal', 'backlink_updated' => 0]) {
    failDocumentContentStoreTest('Recreating should restore the document and invalidate its derived indexes.');
}

$rejectedRecreateId = contentTestId('6666777788889999aaaabbbbccccdddd');
$insertDeleted->execute([$rejectedRecreateId, '복구 롤백']);
try {
    $store->recreate(contentRevision(
        $rejectedRecreateId,
        '777788889999aaaabbbbccccddddeeee',
        '복구 실패 본문',
        revision: 4,
        comment: 'reject',
    ));
    failDocumentContentStoreTest('A failed recreation revision should abort status restoration.');
} catch (PDOException) {
}
$rejectedRecreate = $database->prepare('SELECT status FROM document WHERE uuid=?');
$rejectedRecreate->execute([$rejectedRecreateId]);
if ($rejectedRecreate->fetchColumn() !== 'delete') {
    failDocumentContentStoreTest('A failed recreation must leave the document deleted.');
}
$database->exec('DROP TRIGGER reject_content_revision');

$outerId = contentTestId('88889999aaaabbbbccccddddeeeeffff');
$database->beginTransaction();
$store->create('문서', '외부 트랜잭션', contentRevision(
    $outerId,
    '9999aaaabbbbccccddddeeeeffff0000',
    '외부 본문',
));
if (!$database->inTransaction()) {
    failDocumentContentStoreTest('The content store must not commit a caller-owned transaction.');
}
$database->rollBack();
$outerDocument = $database->prepare('SELECT COUNT(*) FROM document WHERE uuid=?');
$outerDocument->execute([$outerId]);
if ((int) $outerDocument->fetchColumn() !== 0) {
    failDocumentContentStoreTest('The caller should retain control over rolling back content creation.');
}

(new ReflectionProperty(Model::class, 'db'))->setValue(null, $database);
$modelUuid = Document::createWithContent('문서', '모델 생성', '모델 본문', 'model create', null, null);
$modelId = Document::uuid2bin($modelUuid);
$modelDocument = $database->prepare('SELECT status FROM document WHERE uuid=?');
$modelDocument->execute([$modelId]);
if ($modelDocument->fetchColumn() !== 'normal') {
    failDocumentContentStoreTest('Document::createWithContent should use the transactional content store.');
}

$database->prepare("UPDATE document SET status='delete', backlink_updated=1 WHERE uuid=?")->execute([$modelId]);
Document::recreateWithContent($modelUuid, '다시 만든 본문', 'model recreate', null, null, 1);
$modelDocument->execute([$modelId]);
if ($modelDocument->fetchColumn() !== 'normal') {
    failDocumentContentStoreTest('Document::recreateWithContent should restore status with its revision.');
}
$modelLatest = $database->prepare('SELECT content, rev FROM history WHERE document=? ORDER BY rev DESC LIMIT 1');
$modelLatest->execute([$modelId]);
if ($modelLatest->fetch(PDO::FETCH_ASSOC) !== ['content' => '다시 만든 본문', 'rev' => 2]) {
    failDocumentContentStoreTest('Model recreation should append the supplied content as the next revision.');
}

echo 'Document content transaction tests passed.' . PHP_EOL;
