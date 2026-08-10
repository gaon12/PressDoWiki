<?php

declare(strict_types=1);

use PressDo\App\Core\Model;
use PressDo\App\Models\Document;
use PressDo\App\Models\EditRequest;
use PressDo\App\Services\Document\DocumentRevision;
use PressDo\App\Services\Document\PdoDocumentRevisionStore;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failDocumentRevisionStoreTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDOUT, 'Document revision transaction tests skipped: pdo_sqlite is unavailable.' . PHP_EOL);
    exit(0);
}

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec("CREATE TABLE document (uuid BLOB PRIMARY KEY, namespace TEXT NOT NULL DEFAULT '문서', title TEXT NOT NULL DEFAULT '테스트', status TEXT NOT NULL DEFAULT 'normal', backlink_updated INTEGER NOT NULL DEFAULT 1)");
$database->exec(
    'CREATE TABLE history (uuid BLOB PRIMARY KEY, document BLOB NOT NULL, content TEXT, comment TEXT NOT NULL, action TEXT NOT NULL, rev INTEGER NOT NULL, count INTEGER NOT NULL, contributor_m BLOB, contributor_i BLOB, edit_request_uri TEXT, moved_from TEXT, moved_to TEXT)',
);
$database->exec(
    'CREATE TABLE editrequest (urlstr TEXT PRIMARY KEY, status TEXT NOT NULL, acceptrev INTEGER, lastedit INTEGER, executor_m BLOB, executor_i BLOB)',
);

$documentUuid = '00112233-4455-6677-8899-aabbccddeeff';
$documentId = hex2bin(str_replace('-', '', $documentUuid));
$revisionId = hex2bin('11112222333344445555666677778888');
if ($documentId === false || $revisionId === false) {
    failDocumentRevisionStoreTest('Document revision UUID fixtures must be valid hexadecimal.');
}

$insertDocument = $database->prepare('INSERT INTO document (uuid, backlink_updated) VALUES (?, 1)');
$insertDocument->execute([$documentId]);
$store = new PdoDocumentRevisionStore($database);
$store->append(new DocumentRevision(
    revisionId: $revisionId,
    documentId: $documentId,
    content: 'First revision',
    comment: 'created',
    action: 'create',
    revision: 1,
    lengthDelta: 14,
    editRequestSlug: 'request-1',
));

if ((int) $database->query('SELECT COUNT(*) FROM history')->fetchColumn() !== 1) {
    failDocumentRevisionStoreTest('Appending a revision should persist exactly one history row.');
}
if ((int) $database->query('SELECT backlink_updated FROM document')->fetchColumn() !== 0) {
    failDocumentRevisionStoreTest('Appending a revision should invalidate parser-derived indexes.');
}
if ($database->query('SELECT edit_request_uri FROM history')->fetchColumn() !== 'request-1') {
    failDocumentRevisionStoreTest('Edit request provenance should remain attached to its accepted revision.');
}

$database->exec('DELETE FROM history');
$database->exec('UPDATE document SET backlink_updated=1');
$database->exec(<<<'SQL'
    CREATE TRIGGER reject_index_invalidation
    BEFORE UPDATE OF backlink_updated ON document
    WHEN NEW.backlink_updated = 0
      AND EXISTS (SELECT 1 FROM history WHERE document = NEW.uuid AND comment = 'reject')
    BEGIN
        SELECT RAISE(ABORT, 'invalidation rejected');
    END
    SQL);

try {
    $store->append(new DocumentRevision(
        revisionId: hex2bin('22223333444455556666777788889999'),
        documentId: $documentId,
        content: 'Rejected revision',
        comment: 'reject',
        action: 'modify',
        revision: 2,
        lengthDelta: 3,
    ));
    failDocumentRevisionStoreTest('A failed index invalidation should abort the revision append.');
} catch (PDOException) {
}

if ((int) $database->query('SELECT COUNT(*) FROM history')->fetchColumn() !== 0) {
    failDocumentRevisionStoreTest('A failed invalidation should roll back the inserted revision.');
}
if ((int) $database->query('SELECT backlink_updated FROM document')->fetchColumn() !== 1) {
    failDocumentRevisionStoreTest('A failed invalidation should retain the previous index state.');
}
$database->exec('DROP TRIGGER reject_index_invalidation');

$database->beginTransaction();
$store->append(new DocumentRevision(
    revisionId: hex2bin('3333444455556666777788889999aaaa'),
    documentId: $documentId,
    content: 'Caller transaction',
    comment: 'outer',
    action: 'modify',
    revision: 2,
    lengthDelta: 2,
));
if (!$database->inTransaction()) {
    failDocumentRevisionStoreTest('The revision store must not commit a caller-owned transaction.');
}
$database->rollBack();
if ((int) $database->query('SELECT COUNT(*) FROM history')->fetchColumn() !== 0) {
    failDocumentRevisionStoreTest('The caller should retain control over rolling back a revision append.');
}

(new ReflectionProperty(Model::class, 'db'))->setValue(null, $database);
$database->prepare(
    "INSERT INTO history (uuid, document, content, comment, action, rev, count) VALUES (?, ?, '기존 본문', 'initial', 'create', 1, 5)",
)->execute([hex2bin('abababababababababababababababab'), $documentId]);
Document::save($documentUuid, '문서', '테스트', '일반 편집', 'saved', null, null, 1, 0);
if ((int) $database->query('SELECT backlink_updated FROM document')->fetchColumn() !== 0) {
    failDocumentRevisionStoreTest('Document::save should invalidate derived indexes through the revision store.');
}
$latestAction = $database->query('SELECT action FROM history ORDER BY rev DESC LIMIT 1')->fetchColumn();
if ($latestAction !== 'modify') {
    failDocumentRevisionStoreTest('Document::save should append the requested history action.');
}

$database->exec('DELETE FROM history');
$database->exec('UPDATE document SET backlink_updated=1');
$database->exec("INSERT INTO editrequest (urlstr, status) VALUES ('request-2', 'open')");
EditRequest::accept('request-2', [
    'document' => $documentUuid,
    'content' => '편집 요청 본문',
    'comment' => 'accepted',
    'count' => '4',
    'contributor_m' => null,
    'contributor_i' => null,
], null, null, 2);

if ($database->query("SELECT status FROM editrequest WHERE urlstr='request-2'")->fetchColumn() !== 'accepted') {
    failDocumentRevisionStoreTest('Accepting an edit request should update its status.');
}
if ($database->query('SELECT edit_request_uri FROM history')->fetchColumn() !== 'request-2') {
    failDocumentRevisionStoreTest('Accepting an edit request should append a linked document revision.');
}
if ((int) $database->query('SELECT backlink_updated FROM document')->fetchColumn() !== 0) {
    failDocumentRevisionStoreTest('Accepting an edit request should invalidate derived indexes.');
}

echo 'Document revision transaction tests passed.' . PHP_EOL;
