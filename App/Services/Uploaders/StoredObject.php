<?php

declare(strict_types=1);

namespace PressDo\App\Services\Uploaders;

/** Result of an idempotent object-store write. */
final readonly class StoredObject
{
    public function __construct(
        public ObjectKey $key,
        public bool $created,
    ) {}
}
