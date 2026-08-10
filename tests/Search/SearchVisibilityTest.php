<?php

declare(strict_types=1);

use PressDo\App\Core\Model;
use PressDo\App\Models\Search;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failSearchVisibilityTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDOUT, 'Search visibility tests skipped: pdo_sqlite is unavailable.' . PHP_EOL);
    exit(0);
}

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->sqliteCreateFunction(
    'regexp',
    static fn(string $pattern, string $value): int => preg_match('/' . str_replace('/', '\\/', $pattern) . '/u', $value) === 1 ? 1 : 0,
);
$database->exec(
    "CREATE TABLE document (uuid BLOB PRIMARY KEY, namespace TEXT NOT NULL, title TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'normal')",
);
$database->exec('CREATE TABLE search_index (document BLOB PRIMARY KEY, text TEXT NOT NULL)');

$insertDocument = $database->prepare('INSERT INTO document (uuid, namespace, title, status) VALUES (?, ?, ?, ?)');
$insertIndex = $database->prepare('INSERT INTO search_index (document, text) VALUES (?, ?)');
$visibleId = hex2bin('00112233445566778899aabbccddeeff');
$deletedId = hex2bin('ffeeddccbbaa99887766554433221100');
if ($visibleId === false || $deletedId === false) {
    failSearchVisibilityTest('Search visibility UUID fixtures must be valid hexadecimal.');
}

$insertDocument->execute([$visibleId, '문서', '공개 결과', 'normal']);
$insertDocument->execute([$deletedId, '문서', '삭제 결과', 'delete']);
$insertIndex->execute([$visibleId, '공통 검색어 공개 본문']);
$insertIndex->execute([$deletedId, '공통 검색어 삭제 본문']);

(new ReflectionProperty(Model::class, 'db'))->setValue(null, $database);

$titleResults = Search::softSearch('문서', '결과', '결과', '결과');
if ($titleResults !== [['namespace' => '문서', 'title' => '공개 결과']]) {
    failSearchVisibilityTest('Title search must exclude soft-deleted documents.');
}

$contentResults = Search::hardSearch('공통 검색어', 'content', null);
if ($contentResults !== [[
    'namespace' => '문서',
    'title' => '공개 결과',
    'text' => '공통 검색어 공개 본문',
]]) {
    failSearchVisibilityTest('Full-text search must exclude soft-deleted documents even when an index row remains.');
}

echo 'Search visibility tests passed.' . PHP_EOL;
