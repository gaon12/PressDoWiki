<?php

declare(strict_types=1);

use PressDo\App\Services\Mark\NamuMark\Renderer;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failNamuMarkTableParserTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$source = <<<'NAMUMARK'
표 앞 문단

|| '''항목''' || [[대상 문서|안전한 링크]] ||
|| <script>alert(1)</script> || 마지막 셀 ||

표 뒤 문단
NAMUMARK;

$result = (new Renderer())->render($source);
$html = $result['html'];

foreach (
    [
        '<div class="wiki-table-wrap"><table class="wiki-table"><tbody>',
        '<tr><td><strong>항목</strong></td><td><a class="wiki-link"',
        '<tr><td>&lt;script&gt;alert(1)&lt;/script&gt;</td><td>마지막 셀</td></tr>',
        '</tbody></table></div>',
        '<p>표 앞 문단</p>',
        '<p>표 뒤 문단</p>',
    ] as $expected
) {
    if (!str_contains($html, $expected)) {
        failNamuMarkTableParserTest('Missing expected table output: ' . $expected);
    }
}

if (str_contains($html, '<script>') || $result['links']['link'] !== ['대상 문서']) {
    failNamuMarkTableParserTest('Table cells must preserve escaping and backlink extraction.');
}

$malformed = (new Renderer())->render('|| 닫히지 않은 표');
if (!str_contains($malformed['html'], '<p>|| 닫히지 않은 표</p>')) {
    failNamuMarkTableParserTest('Malformed table rows must remain visible as ordinary escaped text.');
}

$wideRow = '||' . implode('||', array_fill(0, 129, 'x')) . '||';
try {
    (new Renderer())->render($wideRow);
    failNamuMarkTableParserTest('Rows exceeding the column limit must be rejected.');
} catch (RuntimeException $error) {
    if (!str_contains($error->getMessage(), '128 cells')) {
        throw $error;
    }
}

$fullRow = '||' . implode('||', array_fill(0, 100, 'x')) . '||';
$tooManyCells = implode("\n", array_fill(0, 100, $fullRow)) . "\n||x||";
try {
    (new Renderer())->render($tooManyCells);
    failNamuMarkTableParserTest('Documents exceeding the table-cell budget must be rejected.');
} catch (RuntimeException $error) {
    if (!str_contains($error->getMessage(), '10,000 table cells')) {
        throw $error;
    }
}

echo 'Clean-room NamuMark table parser tests passed.' . PHP_EOL;
