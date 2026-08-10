<?php

declare(strict_types=1);

use PressDo\App\Infrastructure\Rendering\BladeTemplateRenderer;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failAdminStorageIntegrityTemplateTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$cachePath = sys_get_temp_dir() . '/pressdo-storage-integrity-test-' . bin2hex(random_bytes(8));

try {
    $renderer = new BladeTemplateRenderer($root . '/resources/views', $cachePath);
    $html = $renderer->render('admin.storage_integrity', [
        'wiki' => [
            'page' => [
                'data' => [
                    'items' => [[
                        'document_id' => str_repeat('01', 16),
                        'title' => '파일:"><script>alert(1)</script>.png',
                        'sha256' => str_repeat('ab', 32),
                        'state' => 'missing',
                        'state_label' => '객체 누락',
                        'object_key' => 'ab/' . str_repeat('ab', 32) . '.png',
                    ]],
                    'total' => 30,
                    'offset' => 0,
                    'limit' => 25,
                    'previous_offset' => null,
                    'next_offset' => 25,
                    'error' => null,
                ],
            ],
        ],
    ]);

    if (str_contains($html, '<script>alert(1)</script>')) {
        failAdminStorageIntegrityTemplateTest('Integrity report values must be HTML-escaped.');
    }
    if (!str_contains($html, 'data-integrity-state="missing"') || !str_contains($html, 'offset=25')) {
        failAdminStorageIntegrityTemplateTest('The report should render status and bounded pagination controls.');
    }
} finally {
    if (is_dir($cachePath)) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cachePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($cachePath);
    }
}

echo 'Blade storage integrity template tests passed.' . PHP_EOL;
