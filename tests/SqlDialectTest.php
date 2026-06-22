<?php

require __DIR__.'/../App/Helpers/Database.php';
require __DIR__.'/../App/Helpers/SqlDialect.php';

use PressDo\App\Helpers\Database;
use PressDo\App\Helpers\SqlDialect;

function setDatabaseDriver(string $driver): void
{
    $ref = new ReflectionProperty(Database::class, 'driver');
    $ref->setAccessible(true);
    $ref->setValue(null, $driver);
}

function assertDialect(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message.PHP_EOL);
        fwrite(STDERR, 'Expected: '.var_export($expected, true).PHP_EOL);
        fwrite(STDERR, 'Actual: '.var_export($actual, true).PHP_EOL);
        exit(1);
    }
}

setDatabaseDriver('mysql');
assertDialect(true, SqlDialect::isMysql(), 'MySQL driver should be detected.');
assertDialect('RAND()', SqlDialect::randomOrder(), 'MySQL should use RAND().');
assertDialect('BINARY `title` = ?', SqlDialect::caseSensitiveEquals('`title`'), 'MySQL should use BINARY for case-sensitive comparison.');
assertDialect('LIMIT 10, 25', SqlDialect::limit(10, 25), 'MySQL should use LIMIT offset,count.');
assertDialect('`title`', SqlDialect::quoteIdentifier('title'), 'MySQL should use backtick identifiers.');

setDatabaseDriver('sqlite');
assertDialect(true, SqlDialect::isSqlite(), 'SQLite driver should be detected.');
assertDialect('RANDOM()', SqlDialect::randomOrder(), 'SQLite should use RANDOM().');
assertDialect('`title` = ? COLLATE BINARY', SqlDialect::caseSensitiveEquals('`title`'), 'SQLite should use binary collation.');
assertDialect('LIMIT 10, 25', SqlDialect::limit(10, 25), 'SQLite should keep existing LIMIT offset,count syntax.');

setDatabaseDriver('pgsql');
assertDialect(true, SqlDialect::isPostgresql(), 'PostgreSQL driver should be detected.');
assertDialect('RANDOM()', SqlDialect::randomOrder(), 'PostgreSQL should use RANDOM().');
assertDialect('"title" = ?', SqlDialect::caseSensitiveEquals('`title`'), 'PostgreSQL should use quoted identifiers without MySQL BINARY.');
assertDialect('LIMIT 25 OFFSET 10', SqlDialect::limit(10, 25), 'PostgreSQL should use LIMIT count OFFSET offset.');
assertDialect('"until"', SqlDialect::quoteIdentifier('until'), 'PostgreSQL should use double-quoted identifiers.');

echo "SQL dialect tests passed.".PHP_EOL;
