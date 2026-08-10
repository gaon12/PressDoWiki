<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use RuntimeException;

/** The same binary digest is already owned by another file document. */
final class DuplicateFileException extends RuntimeException {}
