<?php

declare(strict_types=1);

namespace PressDo\App\Services\Uploaders;

use InvalidArgumentException;

/** A normalized, traversal-free object key relative to a configured storage root. */
final readonly class ObjectKey
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = str_replace('\\', '/', trim($value));
        if (
            $normalized === ''
            || str_contains($normalized, "\0")
            || str_starts_with($normalized, '/')
            || preg_match('/\A[A-Za-z]:\//', $normalized) === 1
        ) {
            throw new InvalidArgumentException('An object key must be a non-empty relative path.');
        }

        foreach (explode('/', $normalized) as $segment) {
            if (
                $segment === ''
                || $segment === '.'
                || $segment === '..'
                || preg_match('/\A[A-Za-z0-9._-]+\z/D', $segment) !== 1
            ) {
                throw new InvalidArgumentException('Object key segments contain unsupported characters.');
            }
        }

        $this->value = $normalized;
    }
}
