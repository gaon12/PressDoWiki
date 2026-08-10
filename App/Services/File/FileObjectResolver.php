<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use PressDo\App\Services\Uploaders\ObjectKey;
use PressDo\App\Services\Uploaders\ObjectStorageInterface;

/** Resolves current file keys while retaining read compatibility with old WebP uploads. */
final readonly class FileObjectResolver
{
    public function __construct(private ObjectStorageInterface $storage) {}

    public function resolve(string $sha256, string $documentTitle): ObjectKey
    {
        $candidates = FileObjectLocator::candidateKeys($sha256, $documentTitle);
        foreach ($candidates as $candidate) {
            if ($this->storage->exists($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }
}
