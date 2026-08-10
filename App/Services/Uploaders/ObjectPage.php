<?php

declare(strict_types=1);

namespace PressDo\App\Services\Uploaders;

/** One bounded lexicographic page of stored objects. */
final readonly class ObjectPage
{
    /** @param list<ObjectInfo> $items */
    public function __construct(
        public array $items,
        public ?ObjectKey $nextCursor,
    ) {}
}
