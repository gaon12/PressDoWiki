<?php

declare(strict_types=1);

namespace PressDo\App\Helpers;

use PDO;
use PressDo\App\Core\ProjectPaths;
use PressDo\App\Infrastructure\Files\JsonFile;
use RuntimeException;
use UnexpectedValueException;

final class DefaultConfig
{
    /** @var array<string, mixed> */
    private static array $DefConfig = [];

    private static function init(): void
    {
        if (self::$DefConfig === []) {
            self::$DefConfig = JsonFile::readObject(ProjectPaths::config('config.json'));
        }
    }

    public static function get(string $key): mixed
    {
        self::init();
        $value = self::$DefConfig[$key] ?? null;

        if (is_array($value) && count($value) === 1) {
            return $value[0];
        }

        return $value;
    }

    /** Merge installed database settings into the file defaults. */
    public static function update(PDO $instance): void
    {
        self::init();
        $statement = $instance->query('SELECT `key`, `value` FROM config');
        if ($statement === false) {
            throw new RuntimeException('Default configuration rows could not be queried.');
        }

        $databaseValues = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                throw new UnexpectedValueException('A configuration row must be an array.');
            }

            $key = $row['key'] ?? null;
            $value = $row['value'] ?? null;
            if (!is_string($key) || !is_string($value)) {
                throw new UnexpectedValueException('Configuration keys and values must be strings.');
            }

            $databaseValues[$key][] = $value;
        }

        foreach ($databaseValues as $key => $values) {
            self::$DefConfig[$key] = count($values) === 1 ? $values[0] : $values;
        }
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        self::init();

        return self::$DefConfig;
    }
}
