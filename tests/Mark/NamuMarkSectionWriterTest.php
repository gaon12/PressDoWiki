<?php

declare(strict_types=1);

use PressDo\App\Services\Mark\NamuMark\Renderer;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failNamuMarkSectionWriterTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$source = <<<'NAMUMARK'
도입부

= 상위 제목 =
상위 내용

== 하위 제목 ==
하위 내용

= 다음 제목 =
마지막 내용
NAMUMARK;

$html = (new Renderer())->render($source)['html'];
$document = new DOMDocument();
if (
    @$document->loadHTML(
        '<?xml encoding="UTF-8"><main>' . $html . '</main>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
    ) === false
) {
    failNamuMarkSectionWriterTest('Rendered section HTML must form a valid DOM tree.');
}

$xpath = new DOMXPath($document);
$firstHeading = $xpath->query('//h1[@id="s-1" and contains(concat(" ", normalize-space(@class), " "), " wiki-heading ")]')?->item(0);
if (!$firstHeading instanceof DOMElement) {
    failNamuMarkSectionWriterTest('Headings must expose the bundled skin heading class and stable section ID.');
}

$firstContent = $firstHeading->nextSibling;
if (
    !$firstContent instanceof DOMElement
    || $firstContent->getAttribute('class') !== 'wiki-heading-content'
    || $firstContent->getAttribute('aria-labelledby') !== 's-1'
) {
    failNamuMarkSectionWriterTest('A section content wrapper must be the heading element\'s immediate sibling.');
}

$childHeading = $xpath->query('.//h2[@id="s-2"]', $firstContent)?->item(0);
if (!$childHeading instanceof DOMElement || $childHeading->parentNode !== $firstContent) {
    failNamuMarkSectionWriterTest('A deeper heading and its content must remain inside the parent section.');
}

$nextHeading = $xpath->query('//h1[@id="s-3"]')?->item(0);
if (!$nextHeading instanceof DOMElement || $nextHeading->parentNode === $firstContent) {
    failNamuMarkSectionWriterTest('A same-level heading must close the preceding section hierarchy.');
}

$intro = $xpath->query('//main/p[1]')?->item(0);
if (!$intro instanceof DOMElement || trim($intro->textContent) !== '도입부') {
    failNamuMarkSectionWriterTest('Content before the first heading must remain outside section wrappers.');
}

echo 'Clean-room NamuMark section writer tests passed.' . PHP_EOL;
