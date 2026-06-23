<?php

require __DIR__.'/../App/Helpers/DefaultConfig.php';
require __DIR__.'/../App/Services/Mark/MarkHandler.php';
require __DIR__.'/../App/Services/Mark/Markdown/Loader.php';
require __DIR__.'/../App/Services/Mark/MediaWiki/Loader.php';
require __DIR__.'/../App/Services/Mark/BBCode/Loader.php';

use PressDo\App\Helpers\DefaultConfig;
use PressDo\App\Services\Mark\MarkHandler;

function setDefaultConfigValues(array $values): void
{
    $ref = new ReflectionProperty(DefaultConfig::class, 'DefConfig');
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

setDefaultConfigValues(['wiki.mark' => 'Markdown']);
$markdown = MarkHandler::load('**bold**', []);
assertMarkValue(true, str_contains($markdown['html'], '<strong>bold</strong>'), 'Markdown loader should render through a namespaced Loader class.');

setDefaultConfigValues(['wiki.mark' => 'MediaWiki']);
$mediaWiki = MarkHandler::load("== Heading ==\n\nBody", []);
assertMarkValue(true, str_contains($mediaWiki['html'], '<h'), 'MediaWiki loader should render through a namespaced Loader class.');

setDefaultConfigValues(['wiki.mark' => 'Namumark']);
$alias = MarkHandler::load("== Alias ==\n\nBody", []);
assertMarkValue(true, str_contains($alias['html'], '<h'), 'Legacy Namumark config should fall back to MediaWiki.');

echo "Mark handler tests passed.".PHP_EOL;
