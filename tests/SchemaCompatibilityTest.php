<?php

$root = dirname(__DIR__);
$schemas = [
    'mariadb' => $root.'/templates/database_scheme.sql',
    'sqlite' => $root.'/templates/database_scheme.sqlite.sql',
    'pgsql' => $root.'/templates/database_scheme.pgsql.sql',
];

function schemaTables(string $path): array
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Unable to read schema: {$path}".PHP_EOL);
        exit(1);
    }

    preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?([A-Za-z_][A-Za-z0-9_]*)[`"]?/i', $sql, $matches);
    $tables = array_map('strtolower', $matches[1]);
    sort($tables);

    return array_values(array_unique($tables));
}

$expected = null;
foreach ($schemas as $name => $path) {
    $tables = schemaTables($path);
    if ($expected === null) {
        $expected = $tables;
        continue;
    }

    if ($tables !== $expected) {
        fwrite(STDERR, "Schema table list mismatch for {$name}.".PHP_EOL);
        fwrite(STDERR, 'Expected: '.implode(', ', $expected).PHP_EOL);
        fwrite(STDERR, 'Actual: '.implode(', ', $tables).PHP_EOL);
        exit(1);
    }
}

echo "Schema compatibility tests passed.".PHP_EOL;
