<?php

declare(strict_types=1);

use PressDo\App\Infrastructure\Rendering\BladeTemplateRenderer;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failBladeRendererTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function removeBladeTestDirectory(string $directory): void
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

$cachePath = sys_get_temp_dir() . '/pressdo-blade-test-' . bin2hex(random_bytes(8));

try {
    $renderer = new BladeTemplateRenderer(__DIR__ . '/fixtures', $cachePath);
    $html = $renderer->render('greeting', ['name' => '<Admin>']);

    // Blade preserves the LF from the template on every operating system. The
    // assertion focuses on the rendered markup instead of platform newlines.
    if (trim($html) !== '<h1>Hello, &lt;Admin&gt;!</h1>') {
        failBladeRendererTest('Blade should render the view and escape interpolated values.');
    }

    $compiledTemplates = glob($cachePath . '/*.php');
    if (!is_array($compiledTemplates) || count($compiledTemplates) !== 1) {
        failBladeRendererTest('Blade should compile and cache the rendered template.');
    }

    try {
        $renderer->render('');
        failBladeRendererTest('An empty Blade view name should be rejected.');
    } catch (InvalidArgumentException) {
        // Expected: an empty name is always a programming error.
    }
} finally {
    removeBladeTestDirectory($cachePath);
}

echo 'Blade template renderer tests passed.' . PHP_EOL;
