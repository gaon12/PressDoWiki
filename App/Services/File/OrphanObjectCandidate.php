<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use PressDo\App\Services\Uploaders\ObjectInfo;

final readonly class OrphanObjectCandidate
{
    public function __construct(
        public ObjectInfo $object,
        public int $ageSeconds,
    ) {}
}
