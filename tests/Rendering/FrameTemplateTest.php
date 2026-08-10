<?php

declare(strict_types=1);

use PressDo\App\Infrastructure\Rendering\BladeTemplateRenderer;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failFrameTemplateTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function removeFrameTestDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($directory);
}

$root = dirname(__DIR__, 2);
$cachePath = sys_get_temp_dir() . '/pressdo-frame-test-' . bin2hex(random_bytes(8));

try {
    $renderer = new BladeTemplateRenderer($root . '/resources/views', $cachePath);
    $frameData = [
        'wiki' => [
            'page' => [
                'view_name' => 'notfound',
                'title' => '<Missing>',
                'data' => [],
            ],
        ],
        'config' => [
            'wiki.logo_url' => '/favicon.ico',
            'wiki.site_name' => 'PressDo Test',
            'wiki.front_page' => 'Front Page',
            'wiki.canonical_url' => 'https://example.test',
            'wiki.editor_version' => '0.0.0',
            'wiki.use_captcha' => false,
            'wiki.description' => 'Test wiki',
        ],
        'request_uri' => '/missing',
        'api_config' => [],
        'skinConfig' => [
            'js' => [],
            'additional_heads' => [],
            'body_classes' => ['test-skin'],
        ],
        'skinName' => 'pressdo',
        'body' => '<main>Trusted application body</main>',
    ];
    $html = $renderer->render('frame', $frameData);

    if (!str_contains($html, '&lt;Missing&gt; - PressDo Test')) {
        failFrameTemplateTest('The Blade frame should escape page and site titles.');
    }

    if (!str_contains($html, '<main>Trusted application body</main>')) {
        failFrameTemplateTest('The Blade frame should preserve the already-rendered application body.');
    }

    if (!str_contains($html, 'class="test-skin"')) {
        failFrameTemplateTest('The Blade frame should render configured body classes.');
    }

    $frameData['wiki']['page']['view_name'] = 'edit';
    $editHtml = $renderer->render('frame', $frameData);
    if (!str_contains($editHtml, 'katex@0.18.1') || !str_contains($editHtml, '/src/script/math.js')) {
        failFrameTemplateTest('Edit pages should load the current shared math renderer for live previews.');
    }
    if (str_contains($editHtml, 'katex@0.11.1') || str_contains($editHtml, 'onload="renderMathInElement')) {
        failFrameTemplateTest('The frame should not retain the legacy KaTeX release or inline render handler.');
    }
} finally {
    removeFrameTestDirectory($cachePath);
}

echo 'Blade frame template tests passed.' . PHP_EOL;
