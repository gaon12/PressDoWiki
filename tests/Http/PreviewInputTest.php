<?php

declare(strict_types=1);

use PressDo\App\Core\Request;
use PressDo\App\Http\PreviewInput;
use PressDo\App\Services\Mark\MarkHandler;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failPreviewInputTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$input = PreviewInput::fromRequest(new Request(post: ['text' => '', 'title' => '문서:시험']));
if ($input->text !== '' || $input->title !== '문서:시험') {
    failPreviewInputTest('Preview input should preserve valid scalar fields, including empty document text.');
}

foreach (
    [
        ['title' => 'Missing text'],
        ['text' => [], 'title' => 'Array text'],
        ['text' => 'Missing title'],
        ['text' => 'Array title', 'title' => []],
    ] as $invalidFields
) {
    try {
        PreviewInput::fromRequest(new Request(post: $invalidFields));
        failPreviewInputTest('Preview input should reject missing and array-shaped fields.');
    } catch (InvalidArgumentException) {
    }
}

foreach (
    [
        ['text' => str_repeat('x', MarkHandler::MAX_INPUT_BYTES + 1), 'title' => 'Oversized text'],
        ['text' => 'Oversized title', 'title' => str_repeat('x', PreviewInput::MAX_TITLE_BYTES + 1)],
    ] as $oversizedFields
) {
    try {
        PreviewInput::fromRequest(new Request(post: $oversizedFields));
        failPreviewInputTest('Preview input should reject fields exceeding its resource limits.');
    } catch (LengthException) {
    }
}

echo 'Preview input tests passed.' . PHP_EOL;
