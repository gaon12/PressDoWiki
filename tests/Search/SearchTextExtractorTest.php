<?php

declare(strict_types=1);

use PressDo\App\Services\Search\SearchTextExtractor;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failSearchTextExtractorTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$extractor = new SearchTextExtractor();
$text = $extractor->extract(<<<'HTML'
    <style>.secret { display: block }</style>
    <div id="toc">Duplicated table of contents</div>
    <h1>문서 제목 <a class="wiki-edit-section" href="/edit">[편집]</a></h1>
    <p>Hello&nbsp;<strong>world</strong></p>
    <script>alert('not searchable')</script>
    <ul><li>First</li><li>Second</li></ul>
    HTML);

if ($text !== '문서 제목 Hello world First Second') {
    failSearchTextExtractorTest('Search text should contain visible content without controls or executable elements.');
}

if ($extractor->extract('<p>Malformed <strong>markup') !== 'Malformed markup') {
    failSearchTextExtractorTest('Search text extraction should tolerate repairable HTML fragments.');
}

$largeText = $extractor->extract('<p>' . str_repeat('가', 700_000) . '</p>');
if (strlen($largeText) > SearchTextExtractor::MAX_TEXT_BYTES || mb_check_encoding($largeText, 'UTF-8') !== true) {
    failSearchTextExtractorTest('Large search text should be truncated on a valid UTF-8 byte boundary.');
}

if ($extractor->extract('') !== '') {
    failSearchTextExtractorTest('Empty rendered documents should produce an empty search index value.');
}

echo 'Search text extractor tests passed.' . PHP_EOL;
