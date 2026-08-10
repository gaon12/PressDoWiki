<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use Closure;

/** The small, validated subset of one PHP upload entry used by the application. */
final readonly class UploadedFile
{
    public function __construct(
        public string $originalName,
        public string $temporaryPath,
    ) {}

    /**
     * Convert an untrusted $_FILES entry into typed input.
     *
     * The verifier is injectable so the boundary can be tested without an HTTP
     * server. Production callers must omit it and therefore use is_uploaded_file().
     *
     * @param null|Closure(string): bool $uploadVerifier
     */
    public static function fromPhpFiles(mixed $value, ?Closure $uploadVerifier = null): self
    {
        if (!is_array($value)) {
            throw new UploadValidationException('err_invalid_file');
        }

        $error = $value['error'] ?? null;
        if (!is_int($error)) {
            throw new UploadValidationException('err_invalid_file');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new UploadValidationException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'err_file_toobig',
                UPLOAD_ERR_PARTIAL => 'err_file_upload_partial',
                default => 'err_file_upload_failed',
            });
        }

        $name = $value['name'] ?? null;
        $temporaryPath = $value['tmp_name'] ?? null;
        if (!is_string($name) || $name === '' || !is_string($temporaryPath) || $temporaryPath === '') {
            throw new UploadValidationException('err_invalid_file');
        }

        $verifier = $uploadVerifier ?? static fn(string $path): bool => is_uploaded_file($path);
        if (!$verifier($temporaryPath)) {
            throw new UploadValidationException('err_invalid_file');
        }

        // Browsers normally send only a basename, but never retain a submitted
        // client-side directory if a non-conforming client includes one.
        $normalizedName = str_replace('\\', '/', $name);
        $basename = basename($normalizedName);
        if ($basename === '' || $basename === '.' || $basename === '..') {
            throw new UploadValidationException('err_invalid_file');
        }

        return new self($basename, $temporaryPath);
    }
}
