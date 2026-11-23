<?php

require __DIR__.'/../vendor/autoload.php';

use PressDo\App\Helpers\Config;
use PressDo\App\Services\Mark\MarkHandler;
use PressDo\App\Services\Mark\WikiHtmlSanitizer;

function setMarkConfigValues(array $values): void
{
    $ref = new ReflectionProperty(Config::class, 'Configs');
    $ref->setAccessible(true);
    $ref->setValue(null, $values);
}

function assertMarkValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message.PHP_EOL);
        fwrite(STDERR, 'Expected: '.var_export($expected, true).PHP_EOL);
        fwrite(STDERR, 'Actual: '.var_export($actual, true).PHP_EOL);
        exit(1);
    }
}

setMarkConfigValues(['wiki.mark' => 'Markdown']);
$markdown = MarkHandler::load("**bold**\n\n<script>alert(1)</script>\n\n[unsafe](javascript:alert(1))", []);
assertMarkValue(true, str_contains($markdown->html, '<strong>bold</strong>'), 'Markdown loader should render through a namespaced Loader class.');
assertMarkValue(false, $markdown->links->hasAny(), 'Markdown should receive empty normalized link metadata.');
assertMarkValue(false, str_contains($markdown->html, '<script>'), 'Markdown should not preserve user-provided raw HTML.');
assertMarkValue(true, str_contains($markdown->html, '&lt;script&gt;'), 'Markdown should keep escaped raw HTML visible.');
assertMarkValue(false, str_contains($markdown->html, 'href="javascript:'), 'Markdown should remove unsafe link targets.');

setMarkConfigValues(['wiki.mark' => 'MediaWiki']);
$mediaWiki = MarkHandler::load("== Heading ==\n\nBody", []);
assertMarkValue(true, str_contains($mediaWiki->html, '<h'), 'MediaWiki loader should render through a namespaced Loader class.');

$rawTable = <<<'MARKUP'
Intro
{| class="custom-selector" onmouseover="marker"
|-
| Cell
|}
MARKUP;
$table = MarkHandler::load($rawTable, []);
assertMarkValue(true, str_contains($table->html, '<table>'), 'Legacy wiki tables should retain their table structure.');
assertMarkValue(false, str_contains($table->html, 'onmouseover='), 'Legacy table attributes cannot add event handlers.');
assertMarkValue(false, str_contains($table->html, 'custom-selector'), 'Legacy wiki markup cannot attach application classes.');

$filtered = WikiHtmlSanitizer::sanitize(
    '<p id="application" onclick="marker"><a href="/w/Sample">Sample</a>'
    . '<a href="javascript:marker">unsafe</a><img src="/image.png" onerror="marker"></p>'
    . '<form action="/member/logout"><input name="_csrf"></form><iframe src="https://example.test"></iframe>'
);
assertMarkValue(true, str_contains($filtered, 'href="/w/Sample"'), 'The HTML filter should retain local document links.');
assertMarkValue(true, str_contains($filtered, 'src="/image.png"'), 'The HTML filter should retain local images.');
foreach (['id=', 'onclick=', 'onerror=', 'javascript:', '<form', '<input', '<iframe'] as $blocked) {
    assertMarkValue(false, str_contains($filtered, $blocked), 'Legacy parser output cannot contain ' . $blocked);
}

setMarkConfigValues(['wiki.mark' => 'Namumark']);
$alias = MarkHandler::load("== Alias ==\n\nBody", []);
assertMarkValue(true, str_contains($alias->html, '<h'), 'Legacy Namumark spelling should select the built-in NamuMark renderer.');

try {
    MarkHandler::load(str_repeat('x', 2_097_153), []);
    assertMarkValue(true, false, 'All markup engines should share the same input limit.');
} catch (RuntimeException) {
}

echo "Mark handler tests passed.".PHP_EOL;
