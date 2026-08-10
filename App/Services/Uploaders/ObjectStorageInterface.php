<?php

declare(strict_types=1);

namespace PressDo\App\Services\Uploaders;

interface ObjectStorageInterface
{
    /** Store without overwriting an existing object at the same key. */
    public function store(ObjectKey $key, string $sourcePath): StoredObject;

    /** Check whether an object is currently addressable at this key. */
    public function exists(ObjectKey $key): bool;

    /** List keys lexicographically after the optional cursor. */
    public function listObjects(?ObjectKey $after, int $limit): ObjectPage;

    /** Delete an object created by a failed higher-level transaction. */
    public function delete(ObjectKey $key): void;
}
