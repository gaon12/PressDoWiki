<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use RuntimeException;
use Throwable;

/** Both the database write and its external-object cleanup failed. */
final class FileUploadCompensationException extends RuntimeException
{
    public function __construct(
        public readonly Throwable $persistenceFailure,
        public readonly Throwable $compensationFailure,
    ) {
        parent::__construct(
            'The file database write failed and its newly stored object could not be cleaned up.',
            previous: $persistenceFailure,
        );
    }
}
