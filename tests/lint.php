<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
$compiledPath = tempnam(sys_get_temp_dir(), 'pressdo-lint-');
if ($compiledPath === false) {
    throw new RuntimeException('Unable to create a temporary file for Blade linting.');
}

register_shutdown_function(static fn(): bool => unlink($compiledPath));
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$failed = false;
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)
        || str_contains($path, DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR)) {
        continue;
    }

    if (str_ends_with($path, '.blade.php')) {
        $source = file_get_contents($path);
        if ($source === false || file_put_contents($compiledPath, $compiler->compileString($source)) === false) {
            throw new RuntimeException('Unable to compile "' . $path . '" for linting.');
        }
        echo 'Blade template: ' . $path . PHP_EOL;
        $path = $compiledPath;
    }

    passthru('php -l '.escapeshellarg($path), $code);
    if ($code !== 0) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);
