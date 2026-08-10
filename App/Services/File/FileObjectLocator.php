<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use InvalidArgumentException;
use PressDo\App\Services\Uploaders\ObjectKey;

/** Builds the canonical object key and public path for stored file bytes. */
final class FileObjectLocator
{
    private const EXTENSIONS = ['jpg', 'png', 'gif', 'webp', 'bmp', 'ico'];

    public static function keyForDocument(string $sha256, string $documentTitle): ObjectKey
    {
        return self::keyForExtension($sha256, pathinfo($documentTitle, PATHINFO_EXTENSION));
    }

    public static function keyForExtension(string $sha256, string $extension): ObjectKey
    {
        $digest = strtolower($sha256);
        if (preg_match('/\A[a-f0-9]{64}\z/D', $digest) !== 1) {
            throw new InvalidArgumentException('A stored file digest must be a hexadecimal SHA-256 value.');
        }

        $normalizedExtension = strtolower($extension);
        if ($normalizedExtension === 'jpeg') {
            $normalizedExtension = 'jpg';
        }
        if (!in_array($normalizedExtension, self::EXTENSIONS, true)) {
            throw new InvalidArgumentException('The stored file extension is unsupported.');
        }

        return new ObjectKey(substr($digest, 0, 2) . '/' . $digest . '.' . $normalizedExtension);
    }

    public static function publicPath(string $storageType, ObjectKey $key): string
    {
        return match (strtolower(trim($storageType))) {
            'local' => '/files/' . $key->value,
            's3' => '/' . $key->value,
            default => throw new InvalidArgumentException('The object storage type is unsupported.'),
        };
    }
}
