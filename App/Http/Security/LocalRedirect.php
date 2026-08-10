<?php

declare(strict_types=1);

namespace PressDo\App\Http\Security;

/**
 * Accepts only absolute paths on the current site as redirect destinations.
 *
 * Browsers treat leading double slashes and backslashes as authority markers in
 * several URL contexts. Checking the decoded value closes encoded variants of
 * the same open-redirect trick.
 */
final class LocalRedirect
{
    public static function sanitize(string $target, string $fallback = '/'): string
    {
        if (!self::isLocalPath($fallback)) {
            $fallback = '/';
        }

        return self::isLocalPath($target) ? $target : $fallback;
    }

    private static function isLocalPath(string $target): bool
    {
        if ($target === '' || $target[0] !== '/') {
            return false;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $target) === 1) {
            return false;
        }

        $decoded = rawurldecode($target);
        if (str_starts_with($decoded, '//') || str_contains($decoded, '\\')) {
            return false;
        }

        $parts = parse_url($target);
        if ($parts === false) {
            return false;
        }

        foreach (['scheme', 'host', 'port', 'user', 'pass'] as $authorityPart) {
            if (array_key_exists($authorityPart, $parts)) {
                return false;
            }
        }

        return true;
    }
}
