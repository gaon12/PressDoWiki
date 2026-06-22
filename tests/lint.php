<?php

$root = dirname(__DIR__);
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$failed = false;
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
        continue;
    }

    passthru('php -l '.escapeshellarg($path), $code);
    if ($code !== 0) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);
