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
        return $this->inspect($sha256, $documentTitle)->key;
    }

    public function inspect(string $sha256, string $documentTitle): FileObjectResolution
    {
        $candidates = FileObjectLocator::candidateKeys($sha256, $documentTitle);
        foreach ($candidates as $index => $candidate) {
            if ($this->storage->exists($candidate)) {
                return new FileObjectResolution(
                    $index === 0 ? FileObjectState::Current : FileObjectState::Legacy,
                    $candidate,
                );
            }
        }

        return new FileObjectResolution(FileObjectState::Missing, $candidates[0]);
    }
}
