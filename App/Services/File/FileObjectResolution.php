<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use PressDo\App\Services\Uploaders\ObjectKey;

final readonly class FileObjectResolution
{
    public function __construct(
        public FileObjectState $state,
        public ObjectKey $key,
    ) {}
}
