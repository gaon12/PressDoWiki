<?php

declare(strict_types=1);

namespace PressDo\App\Helpers;

use PressDo\App\Core\ProjectPaths;
use PressDo\App\Infrastructure\Files\JsonFile;
use RuntimeException;

final class Namespaces
{
    public static string $DOCUMENT;

    public static string $FILE;

    public static string $USER;

    public static string $CATEGORY;

    public static string $TEMPLATE;

    /** @var list<string> */
    private static array $Namespaces = [];

    private static function init(): void
    {
        if (self::$Namespaces !== []) {
            return;
        }

        $language = DefaultConfig::get('wiki.language');
        if (!is_string($language) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,31}\z/', $language) !== 1) {
            throw new RuntimeException('The configured wiki language identifier is invalid.');
        }

        $languageDirectory = "language/{$language}";
        $jsonPath = ProjectPaths::config("{$languageDirectory}/namespace.json");
        $phpPath = ProjectPaths::config("{$languageDirectory}/namespace.php");

        if (is_file($jsonPath)) {
            $namespaces = JsonFile::readStringList($jsonPath);
        } elseif (is_file($phpPath)) {
            $namespaces = self::loadPhpList($phpPath);
        } else {
            $namespaces = self::loadPhpList(ProjectPaths::config('language/ko-kr/namespace.php'));
        }

        if (count($namespaces) < 5) {
            throw new RuntimeException('A namespace file must define at least five namespaces.');
        }

        self::$DOCUMENT = $namespaces[0];
        self::$FILE = $namespaces[1];
        self::$USER = $namespaces[2];
        self::$CATEGORY = $namespaces[3];
        self::$TEMPLATE = $namespaces[4];
        self::$Namespaces = $namespaces;
    }

    public static function document(): string
    {
        self::init();

        return self::$DOCUMENT;
    }

    public static function file(): string
    {
        self::init();

        return self::$FILE;
    }

    public static function user(): string
    {
        self::init();

        return self::$USER;
    }

    public static function category(): string
    {
        self::init();

        return self::$CATEGORY;
    }

    public static function template(): string
    {
        self::init();

        return self::$TEMPLATE;
    }

    /** @return list<string> */
    public static function all(): array
    {
        self::init();

        return self::$Namespaces;
    }

    public static function validate(string $namespace): bool
    {
        self::init();

        return in_array($namespace, self::$Namespaces, true);
    }

    /** @return list<string> */
    private static function loadPhpList(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Namespace file does not exist: {$path}");
        }

        $loaded = (static fn(string $file): mixed => require $file)($path);
        if (!is_array($loaded) || !array_is_list($loaded)) {
            throw new RuntimeException("Namespace file must return a list: {$path}");
        }

        $namespaces = [];
        foreach ($loaded as $namespace) {
            if (!is_string($namespace) || $namespace === '') {
                throw new RuntimeException("Namespace entries must be non-empty strings: {$path}");
            }

            $namespaces[] = $namespace;
        }

        return $namespaces;
    }
}
