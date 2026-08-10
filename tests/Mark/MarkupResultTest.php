<?php

declare(strict_types=1);

use PressDo\App\Services\Mark\MarkupResult;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failMarkupResultTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$minimal = MarkupResult::fromLoaderResult(['html' => '<p>Safe</p>']);
if ($minimal->html !== '<p>Safe</p>' || $minimal->links->hasAny()) {
    failMarkupResultTest('A minimal loader result should receive empty relationship metadata.');
}

$normalized = MarkupResult::fromLoaderResult([
    'html' => '<p>Document</p>',
    'editor_comment' => 'generated note',
    'categories' => ['Legacy', 'Legacy'],
    'links' => [
        'link' => ['Target', 'Target'],
        'redirect' => [],
        'include' => [],
        'file' => [],
    ],
]);

if ($normalized->links->documents !== ['Target']) {
    failMarkupResultTest('Document relationships should be deduplicated at the loader boundary.');
}

if ($normalized->links->categories !== ['Legacy' => []]) {
    failMarkupResultTest('Legacy category lists should normalize to the engine category map.');
}

if ($normalized->editorComment !== 'generated note') {
    failMarkupResultTest('Optional editor comments should remain available to edit-request pages.');
}

foreach (
    [
        ['html' => null],
        ['html' => '', 'links' => 'invalid'],
        ['html' => '', 'links' => ['link' => ['valid', 42]]],
        ['html' => '', 'categories' => ['Category' => 'invalid']],
    ] as $invalidResult
) {
    try {
        MarkupResult::fromLoaderResult($invalidResult);
        failMarkupResultTest('Malformed loader output must be rejected before reaching a controller.');
    } catch (RuntimeException) {
    }
}

echo 'Markup result boundary tests passed.' . PHP_EOL;
