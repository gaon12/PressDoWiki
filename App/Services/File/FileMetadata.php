<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use InvalidArgumentException;

/** Immutable metadata stored for one uploaded file document. */
final readonly class FileMetadata
{
    public function __construct(
        public string $documentId,
        public string $sha256,
        public int $width,
        public int $height,
    ) {
        if (strlen($documentId) !== 16) {
            throw new InvalidArgumentException('A file document ID must contain exactly 16 bytes.');
        }
        if (strlen($sha256) !== 32) {
            throw new InvalidArgumentException('A file SHA-256 digest must contain exactly 32 bytes.');
        }
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('File image dimensions must be positive integers.');
        }
    }
}
