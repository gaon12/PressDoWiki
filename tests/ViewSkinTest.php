<?php

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../App/Core/View.php';

use PressDo\App\Core\View;

function assertViewSkinValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message.PHP_EOL);
        fwrite(STDERR, 'Expected: '.var_export($expected, true).PHP_EOL);
        fwrite(STDERR, 'Actual: '.var_export($actual, true).PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$previousDirectory = getcwd();
chdir($root.'/public');

try {
    $view = new View();

    $skinExists = new ReflectionMethod($view, 'skinExists');
    assertViewSkinValue(true, $skinExists->invoke($view, 'pressdo'), 'Pressdo skin should be available.');

    $renderPhpTemplate = new ReflectionMethod($view, 'renderPhpTemplate');
    $html = $renderPhpTemplate->invoke($view, 'skins/pressdo/layout.php', [
        'innerLayout' => '<section>Body</section>',
    ]);

    assertViewSkinValue(
        "<main class=\"pressdo-skin\">\n    <section>Body</section></main>",
        trim((string) $html),
        'Pressdo PHP layout should render the inner layout.'
    );
} finally {
    if ($previousDirectory !== false) {
        chdir($previousDirectory);
    }
}

echo "View skin tests passed.".PHP_EOL;
