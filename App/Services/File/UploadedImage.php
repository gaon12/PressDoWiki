<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

/** Image facts derived from server-side inspection of the uploaded bytes. */
final readonly class UploadedImage
{
    public function __construct(
        public UploadedFile $file,
        public string $extension,
        public string $sha256,
        public int $width,
        public int $height,
    ) {}

    public function objectKey(): string
    {
        return FileObjectLocator::keyForExtension($this->sha256, $this->extension)->value;
    }
}
