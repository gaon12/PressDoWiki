<?php

declare(strict_types=1);

namespace PressDo\App\Helpers;

use PressDo\App\Core\ProjectPaths;
use PressDo\App\Infrastructure\Files\JsonFile;
use RuntimeException;

final class Languages
{
    /** @var array<string, mixed> */
    private static array $Languages = [];

    private static function init(): void
    {
        if (self::$Languages !== []) {
            return;
        }

        $language = DefaultConfig::get('wiki.language');
        if (!is_string($language) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,31}\z/', $language) !== 1) {
            throw new RuntimeException('The configured wiki language identifier is invalid.');
        }

        self::$Languages = JsonFile::readObject(
            ProjectPaths::config("language/{$language}/string.json"),
        );
    }

    public static function get(string ...$keys): mixed
    {
        self::init();
        $value = self::$Languages;
        foreach ($keys as $key) {
            if (!is_array($value)) {
                return null;
            }

            $value = $value[$key] ?? null;
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        self::init();

        return self::$Languages;
    }
}
