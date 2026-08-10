<?php

declare(strict_types=1);

use PressDo\App\Core\Model;
use PressDo\App\Models\Document;
use PressDo\App\Services\Document\DocumentRevision;
use PressDo\App\Services\Document\PdoDocumentDeletionStore;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failDocumentDeletionStoreTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDOUT, 'Document deletion transaction tests skipped: pdo_sqlite is unavailable.' . PHP_EOL);
    exit(0);
}

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec(
    "CREATE TABLE document (uuid BLOB PRIMARY KEY, status TEXT NOT NULL DEFAULT 'normal', backlink_updated INTEGER NOT NULL DEFAULT 0)",
);
$database->exec(
    'CREATE TABLE history (uuid BLOB PRIMARY KEY, document BLOB NOT NULL, content TEXT, comment TEXT NOT NULL, action TEXT NOT NULL, rev INTEGER NOT NULL, count INTEGER NOT NULL, contributor_m BLOB, contributor_i BLOB, edit_request_uri TEXT)',
);
$database->exec('CREATE TABLE search_index (document BLOB PRIMARY KEY, text TEXT NOT NULL)');
$database->exec('CREATE TABLE links (namespace TEXT NOT NULL, title TEXT NOT NULL, from_uuid BLOB NOT NULL, type TEXT NOT NULL)');

/** @return string A validated 16-byte test identifier. */
function deletionTestId(string $hex): string
{
    $id = hex2bin($hex);
    if ($id === false || strlen($id) !== 16) {
        failDocumentDeletionStoreTest('Document deletion UUID fixtures must contain 32 hexadecimal characters.');
    }

    return $id;
}

/** Seed one visible document with both kinds of derived indexes. */
function seedDeletionDocument(PDO $database, string $documentId, string $searchText = 'indexed'): void
{
    $insertDocument = $database->prepare("INSERT INTO document (uuid, status, backlink_updated) VALUES (?, 'normal', 1)");
    $insertDocument->execute([$documentId]);
    $insertSearch = $database->prepare('INSERT INTO search_index (document, text) VALUES (?, ?)');
    $insertSearch->execute([$documentId, $searchText]);
    $insertLink = $database->prepare("INSERT INTO links (namespace, title, from_uuid, type) VALUES ('문서', '대상', ?, 'link')");
    $insertLink->execute([$documentId]);
}

function deletionRevision(string $documentId, string $revisionHex, int $revision = 2): DocumentRevision
{
    return new DocumentRevision(
        revisionId: deletionTestId($revisionHex),
        documentId: $documentId,
        content: null,
        comment: 'deleted',
        action: 'delete',
        revision: $revision,
        lengthDelta: -12,
    );
}

$store = new PdoDocumentDeletionStore($database);
$documentId = deletionTestId('00112233445566778899aabbccddeeff');
seedDeletionDocument($database, $documentId);
$store->delete(deletionRevision($documentId, '11112222333344445555666677778888'));

$document = $database->query('SELECT status, backlink_updated FROM document')->fetch(PDO::FETCH_ASSOC);
if ($document !== ['status' => 'delete', 'backlink_updated' => 1]) {
    failDocumentDeletionStoreTest('Deleting a document should mark it deleted with synchronized empty indexes.');
}
if ((int) $database->query('SELECT COUNT(*) FROM search_index')->fetchColumn() !== 0) {
    failDocumentDeletionStoreTest('Deleting a document should remove its full-text index row.');
}
if ((int) $database->query('SELECT COUNT(*) FROM links')->fetchColumn() !== 0) {
    failDocumentDeletionStoreTest('Deleting a document should remove its outgoing links.');
}
$history = $database->query('SELECT content, action, rev, count FROM history')->fetch(PDO::FETCH_ASSOC);
if ($history !== ['content' => null, 'action' => 'delete', 'rev' => 2, 'count' => -12]) {
    failDocumentDeletionStoreTest('Deleting a document should append the expected content-free deletion revision.');
}

try {
    $store->delete(deletionRevision($documentId, '22223333444455556666777788889999', 3));
    failDocumentDeletionStoreTest('Deleting an already deleted document should fail.');
} catch (RuntimeException) {
}
if ((int) $database->query('SELECT COUNT(*) FROM history')->fetchColumn() !== 1) {
    failDocumentDeletionStoreTest('A repeated deletion must not append another revision.');
}

$rollbackId = deletionTestId('3333444455556666777788889999aaaa');
seedDeletionDocument($database, $rollbackId, 'reject');
$database->exec(<<<'SQL'
    CREATE TRIGGER reject_search_cleanup
    BEFORE DELETE ON search_index
    WHEN OLD.text = 'reject'
    BEGIN
        SELECT RAISE(ABORT, 'search cleanup rejected');
    END
    SQL);

try {
    $store->delete(deletionRevision($rollbackId, '444455556666777788889999aaaabbbb'));
    failDocumentDeletionStoreTest('A failed index cleanup should abort deletion.');
} catch (PDOException) {
}

$rollbackStatus = $database->prepare('SELECT status FROM document WHERE uuid=?');
$rollbackStatus->execute([$rollbackId]);
if ($rollbackStatus->fetchColumn() !== 'normal') {
    failDocumentDeletionStoreTest('A failed cleanup should roll back the document status.');
}
$rollbackHistory = $database->prepare('SELECT COUNT(*) FROM history WHERE document=?');
$rollbackHistory->execute([$rollbackId]);
if ((int) $rollbackHistory->fetchColumn() !== 0) {
    failDocumentDeletionStoreTest('A failed cleanup should roll back the deletion revision.');
}
$rollbackIndex = $database->prepare('SELECT COUNT(*) FROM search_index WHERE document=?');
$rollbackIndex->execute([$rollbackId]);
if ((int) $rollbackIndex->fetchColumn() !== 1) {
    failDocumentDeletionStoreTest('A failed cleanup should retain the previous search index.');
}
$database->exec('DROP TRIGGER reject_search_cleanup');

$outerId = deletionTestId('55556666777788889999aaaabbbbcccc');
seedDeletionDocument($database, $outerId);
$database->beginTransaction();
$store->delete(deletionRevision($outerId, '6666777788889999aaaabbbbccccdddd'));
if (!$database->inTransaction()) {
    failDocumentDeletionStoreTest('The deletion store must not commit a caller-owned transaction.');
}
$database->rollBack();
$outerStatus = $database->prepare('SELECT status FROM document WHERE uuid=?');
$outerStatus->execute([$outerId]);
if ($outerStatus->fetchColumn() !== 'normal') {
    failDocumentDeletionStoreTest('The caller should retain control over rolling back a deletion.');
}

$modelUuid = '77778888-9999-aaaa-bbbb-ccccddddeeee';
$modelId = deletionTestId(str_replace('-', '', $modelUuid));
seedDeletionDocument($database, $modelId);
(new ReflectionProperty(Model::class, 'db'))->setValue(null, $database);
Document::delete($modelUuid, null, null, 7, 1, 'model deletion');
$modelStatus = $database->prepare('SELECT status FROM document WHERE uuid=?');
$modelStatus->execute([$modelId]);
if ($modelStatus->fetchColumn() !== 'delete') {
    failDocumentDeletionStoreTest('Document::delete should use the transactional deletion store.');
}

echo 'Document deletion transaction tests passed.' . PHP_EOL;
