<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use PressDo\App\Services\Document\DocumentRevision;
use PressDo\App\Services\Uploaders\ObjectKey;

/** All validated inputs required to coordinate one external object and DB write. */
final readonly class PendingFileUpload
{
    public function __construct(
        public string $namespace,
        public string $title,
        public string $sourcePath,
        public ObjectKey $objectKey,
        public DocumentRevision $revision,
        public FileMetadata $metadata,
    ) {}
}
