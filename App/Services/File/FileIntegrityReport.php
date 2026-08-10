<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

final readonly class FileIntegrityReport
{
    /** @param list<FileIntegrityItem> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $offset,
        public int $limit,
    ) {}
}
