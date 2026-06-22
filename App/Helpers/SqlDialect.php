<?php
namespace PressDo\App\Helpers;

class SqlDialect
{
    public static function isMysql(): bool
    {
        return in_array(Database::getDriver(), ['mysql', 'mariadb'], true);
    }

    public static function isSqlite(): bool
    {
        return Database::getDriver() === 'sqlite';
    }

    public static function isPostgresql(): bool
    {
        return Database::getDriver() === 'pgsql';
    }

    public static function randomOrder(): string
    {
        return self::isMysql() ? 'RAND()' : 'RANDOM()';
    }

    public static function caseSensitiveEquals(string $column): string
    {
        if (self::isMysql()) {
            return 'BINARY '.$column.' = ?';
        }

        if (self::isPostgresql()) {
            return self::quoteIdentifier($column).' = ?';
        }

        return "$column = ? COLLATE BINARY";
    }

    public static function activeUntil(?string $column = null): string
    {
        $column ??= self::quoteIdentifier('until');
        $column = self::normalizeIdentifier($column);

        return "($column >= ".time()." OR $column = 0)";
    }

    public static function limit(int $offset, int $count): string
    {
        $offset = max(0, $offset);
        $count = max(1, $count);

        if (self::isPostgresql()) {
            return "LIMIT $count OFFSET $offset";
        }

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

    public static function quoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier, "`\" \t\n\r\0\x0B");

        if (self::isPostgresql()) {
            return '"'.str_replace('"', '""', $identifier).'"';
        }

        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private static function normalizeIdentifier(string $identifier): string
    {
        if (!self::isPostgresql()) {
            return $identifier;
        }

        return preg_replace('/`([^`]+)`/', '"$1"', $identifier) ?? $identifier;
    }
}
