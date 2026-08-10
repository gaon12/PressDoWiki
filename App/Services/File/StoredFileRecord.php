<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use InvalidArgumentException;

/** Database metadata required to audit one stored file object. */
final readonly class StoredFileRecord
{
    public function __construct(
        public string $documentId,
        public string $sha256,
        public string $namespace,
        public string $title,
    ) {
        if (strlen($documentId) !== 16 || strlen($sha256) !== 32) {
            throw new InvalidArgumentException('Stored file identifiers have an invalid binary length.');
        }
    }

    public function fullTitle(): string
    {
        return $this->namespace . ':' . $this->title;
    }
}
