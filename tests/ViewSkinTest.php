<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PressDo\App\Core\View;

function assertViewSkinValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assertViewSkinContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected to find: ' . var_export($needle, true) . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$previousDirectory = getcwd();
chdir($root . '/public');

try {
    $view = new View();

    $skinExists = new ReflectionMethod($view, 'skinExists');
    assertViewSkinValue(true, $skinExists->invoke($view, 'pressdo'), 'The bundled PressDo skin should be available.');
    assertViewSkinValue(false, $skinExists->invoke($view, '../config'), 'Skin names must not allow path traversal.');

    $view->params = [
        'wiki' => [
            'session' => ['member' => null],
            'page' => ['title' => '<Unsafe title>', 'data' => []],
        ],
        'config' => ['wiki.site_name' => '<PressDo>', 'wiki.front_page' => 'Front Page'],
        'uri_data' => ['page' => 'wiki', 'title' => 'Test Document'],
        'lang' => ['page' => [], 'auth' => [], 'nav' => []],
        'search_query' => '"><script>alert(1)</script>',
        'innerLayout' => '<section>Trusted application body</section>',
    ];

    $renderSkinLayout = new ReflectionMethod($view, 'renderSkinLayout');
    $html = (string) $renderSkinLayout->invoke($view, 'pressdo');

    assertViewSkinContains('<section>Trusted application body</section>', $html, 'The Blade skin should preserve the rendered page body.');
    assertViewSkinContains('&lt;PressDo&gt;', $html, 'The Blade skin should escape the site name.');
    assertViewSkinContains('&lt;script&gt;alert(1)&lt;/script&gt;', $html, 'The Blade skin should escape the search query.');

    foreach (['pd-wrapper', 'pd-header', 'pd-main', 'pd-footer', 'pd-content'] as $class) {
        assertViewSkinContains($class, $html, "The Blade skin should include an element with class '{$class}'.");
    }
} finally {
    if ($previousDirectory !== false) {
        chdir($previousDirectory);
    }
}

echo 'View skin tests passed.' . PHP_EOL;
