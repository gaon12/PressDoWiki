<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

/** Validates image signatures and names without requiring GD or trusting MIME input. */
final class UploadedImageInspector
{
    public function inspect(UploadedFile $file, string $documentName): UploadedImage
    {
        if (!is_file($file->temporaryPath) || !is_readable($file->temporaryPath)) {
            throw new UploadValidationException('err_invalid_file');
        }

        // getimagesize reads the file signature. It does not trust the browser's
        // Content-Type header and is available even when the optional GD image
        // manipulation extension is not installed.
        $size = @getimagesize($file->temporaryPath);
        if ($size === false) {
            throw new UploadValidationException('err_invalid_file');
        }

        $extension = $this->extensionForImageType($size[2]);
        $clientExtension = $this->normalizedExtension($file->originalName);
        $documentExtension = $this->normalizedExtension($documentName);
        if ($clientExtension !== $extension || $documentExtension !== $extension) {
            throw new UploadValidationException('err_invalid_fileext');
        }

        $sha256 = hash_file('sha256', $file->temporaryPath);
        if ($sha256 === false) {
            throw new UploadValidationException('err_invalid_file');
        }

        return new UploadedImage($file, $extension, $sha256, $size[0], $size[1]);
    }

    private function extensionForImageType(int $type): string
    {
        return match ($type) {
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_BMP => 'bmp',
            IMAGETYPE_ICO => 'ico',
            IMAGETYPE_WEBP => 'webp',
            // SVG deliberately remains unsupported because it is active XML
            // content and needs a dedicated sanitizer and response policy.
            default => throw new UploadValidationException('err_invalid_file'),
        };
    }

    private function normalizedExtension(string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }
}
