<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

final readonly class FileIntegrityItem
{
    public function __construct(
        public StoredFileRecord $file,
        public FileObjectResolution $object,
    ) {}
}
