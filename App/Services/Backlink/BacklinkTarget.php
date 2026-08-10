<?php

declare(strict_types=1);

namespace PressDo\App\Services\Backlink;

use InvalidArgumentException;

/** A validated row waiting to be written to the backlink index. */
final readonly class BacklinkTarget
{
    public function __construct(
        public string $namespace,
        public string $title,
        public BacklinkType $type,
    ) {
        if ($namespace === '') {
            throw new InvalidArgumentException('A backlink namespace cannot be empty.');
        }

        if ($title === '') {
            throw new InvalidArgumentException('A backlink title cannot be empty.');
        }
    }
}
