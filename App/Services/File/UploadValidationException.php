<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use RuntimeException;

/** A safe, localizable validation failure that can be shown to an uploader. */
final class UploadValidationException extends RuntimeException
{
    public function __construct(public readonly string $messageKey)
    {
        parent::__construct($messageKey);
    }
}
