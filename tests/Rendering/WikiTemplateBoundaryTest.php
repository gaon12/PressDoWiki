<?php

declare(strict_types=1);

use PressDo\App\Core\Skin;
use PressDo\App\Core\View;
use PressDo\App\Infrastructure\Rendering\BladeTemplateRenderer;
use PressDo\App\Shared\Rendering\TemplateRenderer;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failWikiTemplateBoundaryTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function removeWikiTemplateTestDirectory(string $directory): void
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

final class WikiViewRecordingRenderer implements TemplateRenderer
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
$cachePath = sys_get_temp_dir() . '/pressdo-wiki-template-test-' . bin2hex(random_bytes(8));

try {
    $renderer = new BladeTemplateRenderer($root . '/resources/views', $cachePath);
    $params = [
        'wiki' => [
            'page' => [
                'data' => [
                    'user' => false,
                    'document' => [
                        'namespace' => '파일',
                        'title' => '"><script>title()</script>.png',
                        'forceShowNamespace' => null,
                        'content' => '<p data-rendered-markup>신뢰된 파서 출력</p>',
                        'categories' => ['"><script>category()</script>' => ['safe', '"><b>class</b>']],
                    ],
                    'file_endpoint' => '/aa/image.png?x="><script>source()</script>',
                    'transparent_img' => '<script>obsoletePlaceholder()</script>',
                    'category_documents' => [
                        '문서' => [
                            'count' => '1',
                            '가' => [[
                                'document' => [
                                    'namespace' => '문서',
                                    'title' => '"><script>entry()</script>',
                                    'forceShowNamespace' => false,
                                ],
                            ]],
                        ],
                    ],
                ],
            ],
        ],
        'config' => ['storage.host' => 'https://files.example.test'],
        'lang' => [
            'msg' => ['error' => '오류', 'alert' => '알림'],
            'category' => [
                'subcategories' => '하위 분류',
                'sub_something' => '"%1$s" 분류에 속하는 %2$s',
                'count' => '전체 %s개 문서',
            ],
        ],
        'error' => ['errbox' => true, 'message' => '"><script>error()</script>'],
        'alert' => ['alertbox' => true, 'message' => '"><script>alert()</script>'],
    ];

    $html = $renderer->render('wiki', $params);
    if (!str_contains($html, '<p data-rendered-markup>신뢰된 파서 출력</p>')) {
        failWikiTemplateBoundaryTest('The wiki view should emit already-rendered markup exactly once.');
    }
    if (substr_count($html, 'data-rendered-markup') !== 1) {
        failWikiTemplateBoundaryTest('Rendered document markup must not be duplicated.');
    }
    foreach (['title()', 'category()', 'source()', 'entry()', 'error()', 'alert()', 'obsoletePlaceholder()'] as $payload) {
        if (str_contains($html, "<script>{$payload}</script>")) {
            failWikiTemplateBoundaryTest("Dynamic wiki value was not escaped: {$payload}");
        }
    }
    if (
        !str_contains($html, 'decoding="async"')
        || !str_contains($html, 'alt="파일:&quot;&gt;&lt;script&gt;title()&lt;/script&gt;.png"')
        || !str_contains($html, '/w/%22%3E%3Cscript%3Eentry%28%29%3C%2Fscript%3E')
    ) {
        failWikiTemplateBoundaryTest('The Blade wiki view should render safe file and category-document links.');
    }

    $params['wiki']['page']['data'] = [
        'user' => true,
        'document' => ['namespace' => '사용자', 'title' => 'Admin', 'content' => ''],
        'userData' => [
            'admin' => true,
            'block' => [
                'blocked' => true,
                'seq' => 7,
                'datetime' => 1_700_000_000,
                'until' => '0',
                'memo' => '"><script>memo()</script>',
            ],
        ],
        'category_documents' => [],
    ];
    $params['lang']['msg'] += [
        'this_is_admin' => '특수 권한 사용자',
        'this_is_blocked' => '차단된 사용자',
        'this_is_blocked_user' => '%1$s / %2$s',
        'b_forever' => '영구적으로',
    ];
    $params['lang']['blocked_reason'] = '차단 사유';
    $userHtml = $renderer->render('wiki', $params);
    if (
        !str_contains($userHtml, 'wiki-user-admin')
        || !str_contains($userHtml, 'wiki-user-blocked')
        || str_contains($userHtml, '<script>memo()</script>')
    ) {
        failWikiTemplateBoundaryTest('User status notices should render without executable inline data.');
    }

    $recordingRenderer = new WikiViewRecordingRenderer();
    $view = new View([], $recordingRenderer);
    $view->skin = new Skin('pressdo', []);
    $view->params = [
        'uri_data' => ['page' => 'wiki', 'menu' => '', 'action' => ''],
        'wiki' => ['page' => ['view_name' => 'wiki', 'data' => []]],
    ];
    $view->renderPage();
    if ($recordingRenderer->views !== ['wiki', 'skins.pressdo', 'frame']) {
        failWikiTemplateBoundaryTest('Wiki reads should use Blade for the page, bundled skin, and frame without Latte.');
    }
} finally {
    removeWikiTemplateTestDirectory($cachePath);
}

echo 'Blade wiki template boundary tests passed.' . PHP_EOL;
