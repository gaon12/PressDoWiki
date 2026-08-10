<?php

declare(strict_types=1);

namespace PressDo\App\Services\Uploaders;

final class ObjectStorageFactory
{
    public static function create(string $name): ObjectStorageInterface
    {
        return match (strtolower(trim($name))) {
            'local' => new Local(),
            's3' => new S3(),
            default => throw new StorageException("Unsupported object storage type: {$name}"),
        };
    }
}
