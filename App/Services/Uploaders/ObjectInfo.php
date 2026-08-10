<?php

declare(strict_types=1);

namespace PressDo\App\Services\Uploaders;

use InvalidArgumentException;

/** Immutable metadata returned by a bounded object inventory request. */
final readonly class ObjectInfo
{
    public function __construct(
        public ObjectKey $key,
        public int $lastModified,
        public int $size,
    ) {
        if ($lastModified < 0 || $size < 0) {
            throw new InvalidArgumentException('Object timestamps and sizes cannot be negative.');
        }
    }
}
