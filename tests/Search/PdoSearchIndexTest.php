<?php

declare(strict_types=1);

use PressDo\App\Services\Search\PdoSearchIndex;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failPdoSearchIndexTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDOUT, 'Search index persistence tests skipped: pdo_sqlite is unavailable.' . PHP_EOL);
    exit(0);
}

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec('CREATE TABLE search_index (document BLOB NOT NULL PRIMARY KEY, text TEXT NOT NULL)');
$index = new PdoSearchIndex($database);
$documentId = hex2bin('ffeeddccbbaa99887766554433221100');
if ($documentId === false) {
    failPdoSearchIndexTest('The search index UUID fixture must be valid hexadecimal.');
}

$index->put($documentId, 'First revision');
if ($database->query('SELECT text FROM search_index')->fetchColumn() !== 'First revision') {
    failPdoSearchIndexTest('The first document render should create its missing search index row.');
}

$index->put($documentId, '두 번째 리비전');
if ($database->query('SELECT text FROM search_index')->fetchColumn() !== '두 번째 리비전') {
    failPdoSearchIndexTest('A later document render should replace its current search index text.');
}
if ((int) $database->query('SELECT COUNT(*) FROM search_index')->fetchColumn() !== 1) {
    failPdoSearchIndexTest('Search index upserts should retain one row per document.');
}

$database->beginTransaction();
$index->put($documentId, 'Rolled back');
$database->rollBack();
if ($database->query('SELECT text FROM search_index')->fetchColumn() !== '두 번째 리비전') {
    failPdoSearchIndexTest('Search index writes should remain controlled by a caller-owned transaction.');
}

try {
    $index->put('invalid', 'Rejected');
    failPdoSearchIndexTest('Search index writes should reject invalid binary document IDs.');
} catch (InvalidArgumentException) {
}

echo 'Search index persistence tests passed.' . PHP_EOL;
