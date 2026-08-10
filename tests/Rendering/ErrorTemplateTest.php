<?php

declare(strict_types=1);

use PressDo\App\Core\Skin;
use PressDo\App\Core\View;
use PressDo\App\Infrastructure\Rendering\BladeTemplateRenderer;
use PressDo\App\Shared\Rendering\TemplateRenderer;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failErrorTemplateTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function removeErrorTemplateTestDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}

final class ErrorViewRecordingRenderer implements TemplateRenderer
{
    /** @var list<string> */
    public array $views = [];

    public function render(string $view, array $data = []): string
    {
        $this->views[] = $view;

        return "<{$view}>";
    }
}

$root = dirname(__DIR__, 2);
$cachePath = sys_get_temp_dir() . '/pressdo-error-template-test-' . bin2hex(random_bytes(8));

try {
    $renderer = new BladeTemplateRenderer($root . '/resources/views', $cachePath);
    $html = $renderer->render('error', [
        'wiki' => [
            'page' => [
                'data' => [
                    'code' => 'permission_read',
                    'message' => '읽기 권한이 없습니다.<br><a href="/unsafe"><script>run()</script>ACL</a>',
                ],
            ],
        ],
        'lang' => ['msg' => ['error' => '오류', 'permission_read' => '번역 대체문']],
        'uri_data' => ['title' => '"><script>title()</script>'],
    ]);
    if (
        str_contains($html, '<script>')
        || str_contains($html, 'href="/unsafe"')
        || !str_contains($html, '읽기 권한이 없습니다.')
        || !str_contains($html, '/acl/%22%3E%3Cscript%3Etitle%28%29%3C%2Fscript%3E')
    ) {
        failErrorTemplateTest('Error messages should become escaped plain text with a separately encoded ACL link.');
    }

    $fallbackHtml = $renderer->render('error', [
        'wiki' => ['page' => ['data' => ['code' => 'no_permission']]],
        'lang' => ['msg' => ['error' => '오류', 'no_permission' => '권한이 부족합니다.']],
        'uri_data' => [],
    ]);
    if (!str_contains($fallbackHtml, '권한이 부족합니다.')) {
        failErrorTemplateTest('Error codes without a provided message should use their translated text.');
    }

    $recordingRenderer = new ErrorViewRecordingRenderer();
    $view = new View([], $recordingRenderer);
    $view->skin = new Skin('pressdo', []);
    $view->params = [
        'uri_data' => ['page' => 'wiki', 'menu' => '', 'action' => '', 'title' => '문서'],
        'wiki' => ['page' => ['view_name' => 'error', 'data' => ['code' => 'no_permission']]],
    ];
    $view->renderPage();
    if ($recordingRenderer->views !== ['error', 'skins.pressdo', 'frame']) {
        failErrorTemplateTest('Common error responses should use Blade without invoking Latte.');
    }
} finally {
    removeErrorTemplateTestDirectory($cachePath);
}

echo 'Blade error template tests passed.' . PHP_EOL;
