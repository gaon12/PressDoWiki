<?php

declare(strict_types=1);

use PressDo\App\Core\Model;
use PressDo\App\Helpers\DefaultConfig;
use PressDo\App\Models\Document;
use PressDo\App\Services\Document\DocumentRevision;
use PressDo\App\Services\Document\PdoDocumentMoveStore;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failDocumentMoveStoreTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDOUT, 'Document move transaction tests skipped: pdo_sqlite is unavailable.' . PHP_EOL);
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
$database->exec('CREATE TABLE search_index (document BLOB PRIMARY KEY, text TEXT NOT NULL)');
$database->exec('CREATE TABLE links (namespace TEXT NOT NULL, title TEXT NOT NULL, from_uuid BLOB NOT NULL, type TEXT NOT NULL)');

/** @return string A validated 16-byte test identifier. */
function moveTestId(string $hex): string
{
    $id = hex2bin($hex);
    if ($id === false || strlen($id) !== 16) {
        failDocumentMoveStoreTest('Document move UUID fixtures must contain 32 hexadecimal characters.');
    }

    return $id;
}

function seedMoveDocument(PDO $database, string $documentId, string $title): void
{
    $insert = $database->prepare(
        "INSERT INTO document (uuid, namespace, title, status, backlink_updated) VALUES (?, '문서', ?, 'normal', 1)",
    );
    $insert->execute([$documentId, $title]);
}

function moveRevision(
    string $documentId,
    string $revisionHex,
    string $from,
    string $to,
    string $comment = 'moved',
): DocumentRevision {
    return new DocumentRevision(
        revisionId: moveTestId($revisionHex),
        documentId: $documentId,
        content: null,
        comment: $comment,
        action: 'move',
        revision: 2,
        lengthDelta: 0,
        movedFrom: $from,
        movedTo: $to,
    );
}

$store = new PdoDocumentMoveStore($database);
$documentId = moveTestId('00112233445566778899aabbccddeeff');
seedMoveDocument($database, $documentId, '원본');
$insertSearch = $database->prepare('INSERT INTO search_index (document, text) VALUES (?, ?)');
$insertSearch->execute([$documentId, '기존 검색 본문']);
$insertLink = $database->prepare("INSERT INTO links (namespace, title, from_uuid, type) VALUES ('문서', '대상', ?, 'link')");
$insertLink->execute([$documentId]);

$store->move(
    moveRevision($documentId, '11112222333344445555666677778888', '원본', '이동 대상'),
    '문서',
    '원본',
    '문서',
    '이동 대상',
);

$moved = $database->query('SELECT namespace, title, backlink_updated FROM document')->fetch(PDO::FETCH_ASSOC);
if ($moved !== ['namespace' => '문서', 'title' => '이동 대상', 'backlink_updated' => 1]) {
    failDocumentMoveStoreTest('Moving should change only the document location and retain synchronized indexes.');
}
$history = $database->query('SELECT content, action, count, moved_from, moved_to FROM history')->fetch(PDO::FETCH_ASSOC);
if ($history !== [
    'content' => null,
    'action' => 'move',
    'count' => 0,
    'moved_from' => '원본',
    'moved_to' => '이동 대상',
]) {
    failDocumentMoveStoreTest('Moving should append a content-free revision with title provenance.');
}
if ($database->query('SELECT text FROM search_index')->fetchColumn() !== '기존 검색 본문') {
    failDocumentMoveStoreTest('Moving should preserve the content-derived search index.');
}
if ((int) $database->query('SELECT COUNT(*) FROM links')->fetchColumn() !== 1) {
    failDocumentMoveStoreTest('Moving should preserve outgoing links derived from unchanged content.');
}

$collisionSourceId = moveTestId('22223333444455556666777788889999');
$collisionTargetId = moveTestId('3333444455556666777788889999aaaa');
seedMoveDocument($database, $collisionSourceId, '충돌 원본');
seedMoveDocument($database, $collisionTargetId, '이미 존재');
try {
    $store->move(
        moveRevision($collisionSourceId, '444455556666777788889999aaaabbbb', '충돌 원본', '이미 존재'),
        '문서',
        '충돌 원본',
        '문서',
        '이미 존재',
    );
    failDocumentMoveStoreTest('Moving to an occupied title should fail.');
} catch (PDOException) {
}
$collisionSource = $database->prepare('SELECT title FROM document WHERE uuid=?');
$collisionSource->execute([$collisionSourceId]);
if ($collisionSource->fetchColumn() !== '충돌 원본') {
    failDocumentMoveStoreTest('A title collision should retain the source location.');
}

$rollbackId = moveTestId('55556666777788889999aaaabbbbcccc');
seedMoveDocument($database, $rollbackId, '롤백 원본');
$database->exec(<<<'SQL'
    CREATE TRIGGER reject_move_revision
    BEFORE INSERT ON history
    WHEN NEW.comment = 'reject'
    BEGIN
        SELECT RAISE(ABORT, 'move revision rejected');
    END
    SQL);
try {
    $store->move(
        moveRevision($rollbackId, '6666777788889999aaaabbbbccccdddd', '롤백 원본', '롤백 대상', 'reject'),
        '문서',
        '롤백 원본',
        '문서',
        '롤백 대상',
    );
    failDocumentMoveStoreTest('A failed move revision should abort the title change.');
} catch (PDOException) {
}
$rollbackTitle = $database->prepare('SELECT title FROM document WHERE uuid=?');
$rollbackTitle->execute([$rollbackId]);
if ($rollbackTitle->fetchColumn() !== '롤백 원본') {
    failDocumentMoveStoreTest('A failed revision should roll back the document title.');
}
$database->exec('DROP TRIGGER reject_move_revision');

try {
    $store->move(
        moveRevision($rollbackId, '777788889999aaaabbbbccccddddeeee', '오래된 원본', '새 대상'),
        '문서',
        '오래된 원본',
        '문서',
        '새 대상',
    );
    failDocumentMoveStoreTest('A stale source title should be rejected.');
} catch (RuntimeException) {
}

$outerId = moveTestId('88889999aaaabbbbccccddddeeeeffff');
seedMoveDocument($database, $outerId, '외부 원본');
$database->beginTransaction();
$store->move(
    moveRevision($outerId, '9999aaaabbbbccccddddeeeeffff0000', '외부 원본', '외부 대상'),
    '문서',
    '외부 원본',
    '문서',
    '외부 대상',
);
if (!$database->inTransaction()) {
    failDocumentMoveStoreTest('The move store must not commit a caller-owned transaction.');
}
$database->rollBack();
$outerTitle = $database->prepare('SELECT title FROM document WHERE uuid=?');
$outerTitle->execute([$outerId]);
if ($outerTitle->fetchColumn() !== '외부 원본') {
    failDocumentMoveStoreTest('The caller should retain control over rolling back a move.');
}

$modelUuid = 'aaaabbbb-cccc-dddd-eeee-ffff00001111';
$modelId = moveTestId(str_replace('-', '', $modelUuid));
seedMoveDocument($database, $modelId, '모델 원본');
(new ReflectionProperty(Model::class, 'db'))->setValue(null, $database);
(new ReflectionProperty(DefaultConfig::class, 'DefConfig'))->setValue(null, ['wiki.language' => 'ko-kr']);
Document::move($modelUuid, '모델 원본', '모델 대상', null, null, 1, 'model move');
$modelTitle = $database->prepare('SELECT title FROM document WHERE uuid=?');
$modelTitle->execute([$modelId]);
if ($modelTitle->fetchColumn() !== '모델 대상') {
    failDocumentMoveStoreTest('Document::move should use the transactional move store.');
}

echo 'Document move transaction tests passed.' . PHP_EOL;
