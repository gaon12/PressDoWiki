<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use InvalidArgumentException;

/** Audits one bounded database page without loading every file into memory. */
final readonly class FileIntegrityScanner
{
    public function __construct(
        private PdoFileIntegrityRepository $files,
        private FileObjectResolver $objects,
    ) {}

    public function scan(int $offset, int $limit): FileIntegrityReport
    {
        if ($offset < 0 || $limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Integrity scan pagination is outside the allowed range.');
        }

        $items = [];
        foreach ($this->files->page($offset, $limit) as $file) {
            $items[] = new FileIntegrityItem(
                $file,
                $this->objects->inspect(bin2hex($file->sha256), $file->title),
            );
        }

        return new FileIntegrityReport($items, $this->files->count(), $offset, $limit);
    }
}
