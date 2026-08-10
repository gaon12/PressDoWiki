<?php

declare(strict_types=1);

namespace PressDo\App\Services\Document;

use RuntimeException;

/** The document changed after the editor loaded its base revision. */
final class DocumentConflictException extends RuntimeException {}
