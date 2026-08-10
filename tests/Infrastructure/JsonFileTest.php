<?php

declare(strict_types=1);

use PressDo\App\Infrastructure\Files\JsonFile;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failJsonFileTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$directory = sys_get_temp_dir() . '/pressdo-json-test-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700) && !is_dir($directory)) {
    failJsonFileTest('The JSON test directory could not be created.');
}

try {
    $objectPath = $directory . '/object.json';
    $listPath = $directory . '/list.json';
    $invalidPath = $directory . '/invalid.json';
    file_put_contents($objectPath, '{"name":"PressDo","enabled":true}');
    file_put_contents($listPath, '["문서","파일"]');
    file_put_contents($invalidPath, '{broken');

    if (JsonFile::readObject($objectPath) !== ['name' => 'PressDo', 'enabled' => true]) {
        failJsonFileTest('JSON objects should retain their string-keyed values.');
    }
    if (JsonFile::readStringList($listPath) !== ['문서', '파일']) {
        failJsonFileTest('JSON string lists should retain their ordered values.');
    }

    foreach (
        [
            static fn(): array => JsonFile::readObject($listPath),
            static fn(): array => JsonFile::readStringList($objectPath),
            static fn(): array => JsonFile::readObject($invalidPath),
            static fn(): array => JsonFile::readObject($directory . '/missing.json'),
        ] as $invalidRead
    ) {
        try {
            $invalidRead();
            failJsonFileTest('Invalid JSON files and result shapes should be rejected.');
        } catch (RuntimeException) {
        }
    }
} finally {
    foreach ([$objectPath ?? '', $listPath ?? '', $invalidPath ?? ''] as $path) {
        if ($path !== '' && is_file($path)) {
            unlink($path);
        }
    }
    rmdir($directory);
}

echo 'JSON file tests passed.' . PHP_EOL;
