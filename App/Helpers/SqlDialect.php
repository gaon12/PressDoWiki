<?php
namespace PressDo\App\Helpers;

class SqlDialect
{
    public static function isSqlite(): bool
    {
        return Database::getDriver() === 'sqlite';
    }

    public static function randomOrder(): string
    {
        return self::isSqlite() ? 'RANDOM()' : 'RAND()';
    }

    public static function caseSensitiveEquals(string $column): string
    {
        return self::isSqlite() ? "$column = ? COLLATE BINARY" : "BINARY $column = ?";
    }

    public static function activeUntil(string $column = '`until`'): string
    {
        return "($column >= ".time()." OR $column = 0)";
    }

    public static function limit(int $offset, int $count): string
    {
        $offset = max(0, $offset);
        $count = max(1, $count);

        return "LIMIT $offset, $count";
    }

    public static function orderDirection(string $direction, string $default = 'DESC'): string
    {
        $direction = strtoupper($direction);

        if ($direction === 'ASC' || $direction === 'DESC') {
            return $direction;
        }

        return strtoupper($default) === 'ASC' ? 'ASC' : 'DESC';
    }
}
