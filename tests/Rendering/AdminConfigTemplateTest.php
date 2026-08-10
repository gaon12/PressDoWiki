<?php

declare(strict_types=1);

use PressDo\App\Infrastructure\Rendering\BladeTemplateRenderer;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failAdminConfigTemplateTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$cachePath = sys_get_temp_dir() . '/pressdo-admin-config-test-' . bin2hex(random_bytes(8));

try {
    $renderer = new BladeTemplateRenderer($root . '/resources/views', $cachePath);
    $html = $renderer->render('admin.config', [
        'wiki' => [
            'page' => [
                'data' => [
                    'saved' => false,
                    'token' => '<csrf-token>',
                    'errors' => [],
                    'sections' => [
                        '사이트' => [[
                            'key' => 'wiki.site_name',
                            'label' => '사이트 이름',
                            'description' => '표시할 이름',
                            'type' => 'text',
                            'value' => '"><script>alert(1)</script>',
                            'choices' => [],
                            'error' => null,
                        ]],
                    ],
                ],
            ],
        ],
    ]);

    if (str_contains($html, '<script>alert(1)</script>')) {
        failAdminConfigTemplateTest('Administrator setting values must be HTML-escaped.');
    }

    if (!str_contains($html, 'value="&lt;csrf-token&gt;"')) {
        failAdminConfigTemplateTest('The Blade settings form should escape its CSRF token.');
    }

    if (!str_contains($html, 'name="settings[wiki.site_name]"')) {
        failAdminConfigTemplateTest('The settings form should submit catalogued keys as one object.');
    }
} finally {
    if (is_dir($cachePath)) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cachePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($cachePath);
    }
}

echo 'Blade administrator settings template tests passed.' . PHP_EOL;
