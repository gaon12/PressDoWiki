<?php

declare(strict_types=1);

use PressDo\App\Services\File\FileObjectLocator;
use PressDo\App\Services\File\FileObjectResolver;
use PressDo\App\Services\Uploaders\ObjectKey;
use PressDo\App\Services\Uploaders\ObjectPage;
use PressDo\App\Services\Uploaders\ObjectStorageInterface;
use PressDo\App\Services\Uploaders\StoredObject;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failFileObjectResolverTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

final readonly class ExistingKeyStorage implements ObjectStorageInterface
{
    /** @param list<string> $existing */
    public function __construct(private array $existing) {}

    public function store(ObjectKey $key, string $sourcePath): StoredObject
    {
        return new StoredObject($key, false);
    }

    public function exists(ObjectKey $key): bool
    {
        return in_array($key->value, $this->existing, true);
    }

    public function listObjects(?ObjectKey $after, int $limit): ObjectPage
    {
        return new ObjectPage([], null);
    }

    public function delete(ObjectKey $key): void {}
}

$digest = str_repeat('ab', 32);
[$current, $legacy] = FileObjectLocator::candidateKeys($digest, '사진.png');
if (!str_ends_with($current->value, '.png') || !str_ends_with($legacy->value, '.webp')) {
    failFileObjectResolverTest('PNG documents should expose current and legacy WebP candidates in order.');
}

$currentResolver = new FileObjectResolver(new ExistingKeyStorage([$current->value, $legacy->value]));
if ($currentResolver->resolve($digest, '사진.png')->value !== $current->value) {
    failFileObjectResolverTest('The current-format object must take precedence when both keys exist.');
}
$legacyResolver = new FileObjectResolver(new ExistingKeyStorage([$legacy->value]));
if ($legacyResolver->resolve($digest, '사진.png')->value !== $legacy->value) {
    failFileObjectResolverTest('A legacy WebP object should be used when the current PNG object is absent.');
}
$missingResolver = new FileObjectResolver(new ExistingKeyStorage([]));
if ($missingResolver->resolve($digest, '사진.png')->value !== $current->value) {
    failFileObjectResolverTest('Missing objects should retain the stable current-format URL.');
}

echo 'File object compatibility resolver tests passed.' . PHP_EOL;
