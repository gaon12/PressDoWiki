<?php

declare(strict_types=1);

namespace PressDo\App\Core;

use InvalidArgumentException;

/**
 * Absolute paths for files owned by this PressDoWiki installation.
 *
 * Web requests, CLI commands, workers, and tests may all start with different
 * working directories. Application files must therefore be resolved from the
 * installed project root instead of the process working directory.
 */
final class ProjectPaths
{
    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function app(string $relativePath = ''): string
    {
        return self::below(self::root() . '/App', $relativePath);
    }

    public static function config(string $relativePath = ''): string
    {
        return self::below(self::root() . '/config', $relativePath);
    }

    public static function public(string $relativePath = ''): string
    {
        return self::below(self::root() . '/public', $relativePath);
    }

    public static function resources(string $relativePath = ''): string
    {
        return self::below(self::root() . '/resources', $relativePath);
    }

    public static function variable(string $relativePath = ''): string
    {
        return self::below(self::root() . '/var', $relativePath);
    }

    private static function below(string $basePath, string $relativePath): string
    {
        if ($relativePath === '') {
            return $basePath;
        }

        $normalized = str_replace('\\', '/', $relativePath);
        if (
            str_contains($normalized, "\0")
            || str_starts_with($normalized, '/')
            || preg_match('/\A[A-Za-z]:\//', $normalized) === 1
        ) {
            throw new InvalidArgumentException('Project paths must be relative to their declared directory.');
        }

        $segments = explode('/', $normalized);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Project paths cannot contain empty or traversal segments.');
            }
        }

        return $basePath . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
    }
}
