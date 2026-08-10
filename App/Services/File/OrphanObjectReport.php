<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use PressDo\App\Services\Uploaders\ObjectKey;

final readonly class OrphanObjectReport
{
    /** @param list<OrphanObjectCandidate> $candidates */
    public function __construct(
        public array $candidates,
        public int $scanned,
        public int $ignoredRecent,
        public int $ignoredUnmanaged,
        public ?ObjectKey $nextCursor,
    ) {}
}
