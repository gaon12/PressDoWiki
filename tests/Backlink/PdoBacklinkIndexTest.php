<?php

declare(strict_types=1);

use PressDo\App\Services\Backlink\BacklinkTarget;
use PressDo\App\Services\Backlink\BacklinkType;
use PressDo\App\Services\Backlink\PdoBacklinkIndex;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failBacklinkIndexTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDOUT, 'Backlink transaction tests skipped: pdo_sqlite is unavailable.' . PHP_EOL);
    exit(0);
}

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec('PRAGMA foreign_keys = ON');
$database->exec(
    'CREATE TABLE `document` (`uuid` BLOB PRIMARY KEY, `backlink_updated` INTEGER NOT NULL DEFAULT 0)',
);
$database->exec(
    "CREATE TABLE `links` (`namespace` TEXT NOT NULL, `title` TEXT NOT NULL CHECK (`title` != 'Forbidden'), `from_uuid` BLOB NOT NULL, `type` TEXT NOT NULL, UNIQUE (`namespace`, `title`, `from_uuid`, `type`), FOREIGN KEY (`from_uuid`) REFERENCES `document` (`uuid`))",
);

$documentId = hex2bin('00112233445566778899aabbccddeeff');
if ($documentId === false) {
    failBacklinkIndexTest('The test document UUID fixture must be valid hexadecimal.');
}

$insertDocument = $database->prepare('INSERT INTO `document` (`uuid`) VALUES (?)');
$insertDocument->execute([$documentId]);
$insertLink = $database->prepare(
    "INSERT INTO `links` (`namespace`, `title`, `from_uuid`, `type`) VALUES ('문서', ?, ?, 'link')",
);
$insertLink->execute(['Stale', $documentId]);

$index = new PdoBacklinkIndex($database);
$manyTargets = [];
for ($number = 1; $number <= 205; ++$number) {
    $manyTargets[] = new BacklinkTarget('문서', 'Target ' . $number, BacklinkType::Link);
}
$index->replace($documentId, $manyTargets);

$storedCount = (int) $database->query('SELECT COUNT(*) FROM `links`')->fetchColumn();
if ($storedCount !== 205) {
    failBacklinkIndexTest('Large backlink sets should be replaced in portable insertion chunks.');
}
if ((int) $database->query('SELECT `backlink_updated` FROM `document`')->fetchColumn() !== 1) {
    failBacklinkIndexTest('A successful replacement should mark the backlink index as current.');
}

$database->exec('UPDATE `document` SET `backlink_updated`=0');
$index->replace($documentId, []);
if ((int) $database->query('SELECT COUNT(*) FROM `links`')->fetchColumn() !== 0) {
    failBacklinkIndexTest('An empty parser result should remove relationships from the previous revision.');
}
if ((int) $database->query('SELECT `backlink_updated` FROM `document`')->fetchColumn() !== 1) {
    failBacklinkIndexTest('An empty parser result should still mark the backlink index as current.');
}

$database->exec('UPDATE `document` SET `backlink_updated`=0');
$insertLink->execute(['Preserved', $documentId]);
try {
    $index->replace($documentId, [
        new BacklinkTarget('문서', 'Replacement', BacklinkType::Link),
        new BacklinkTarget('문서', 'Forbidden', BacklinkType::Link),
    ]);
    failBacklinkIndexTest('A rejected backlink row should abort the replacement.');
} catch (PDOException) {
}

$storedTitle = $database->query('SELECT `title` FROM `links`')->fetchColumn();
if ($storedTitle !== 'Preserved') {
    failBacklinkIndexTest('A failed replacement should restore the complete previous backlink index.');
}
if ((int) $database->query('SELECT `backlink_updated` FROM `document`')->fetchColumn() !== 0) {
    failBacklinkIndexTest('A failed replacement must not mark the backlink index as current.');
}

$database->beginTransaction();
$index->replace($documentId, [new BacklinkTarget('문서', 'Outer transaction', BacklinkType::Link)]);
if (!$database->inTransaction()) {
    failBacklinkIndexTest('The backlink writer must not commit a transaction owned by its caller.');
}
$database->rollBack();
if ($database->query('SELECT `title` FROM `links`')->fetchColumn() !== 'Preserved') {
    failBacklinkIndexTest('The caller should retain control over rolling back its transaction.');
}

echo 'Backlink index transaction tests passed.' . PHP_EOL;
