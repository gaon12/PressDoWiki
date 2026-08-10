<?php

declare(strict_types=1);

namespace PressDo\App\Http;

use InvalidArgumentException;

/**
 * Builds local wiki URLs without allowing titles to become path or header data.
 */
final class WikiUrl
{
    private const MAX_TITLE_BYTES = 1_024;

    /**
     * @param array<string, string> $query
     */
    public static function document(string $title, array $query = []): string
    {
        if (
            $title === ''
            || strlen($title) > self::MAX_TITLE_BYTES
            || preg_match('/[\x00-\x1F\x7F]/', $title) === 1
        ) {
            throw new InvalidArgumentException('Wiki document titles must be non-empty, bounded, and free of control characters.');
        }

        $location = '/w/' . rawurlencode($title);
        if ($query === []) {
            return $location;
        }

        return $location . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
