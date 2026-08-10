<?php

require __DIR__.'/../App/Helpers/Config.php';

use PressDo\App\Helpers\Config;

function setConfigValues(array $values): void
{
    $ref = new ReflectionProperty(Config::class, 'Configs');
    $ref->setAccessible(true);
    $ref->setValue(null, $values);
}

function assertConfigValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message.PHP_EOL);
        fwrite(STDERR, 'Expected: '.var_export($expected, true).PHP_EOL);
        fwrite(STDERR, 'Actual: '.var_export($actual, true).PHP_EOL);
        exit(1);
    }
}

function setConfigDb(\PDO $pdo): void
{
    $ref = new ReflectionProperty(Config::class, 'db');
    $ref->setAccessible(true);
    $ref->setValue(null, $pdo);
}

setConfigValues([
    'aclgroup.1.style' => ['color: red;', 'font-weight: bold;'],
    'wiki.site_name' => 'PressDoWiki',
]);

assertConfigValue(
    'color: red; font-weight: bold;',
    Config::get('aclgroup.1.style', ' '),
    'Config::get should implode array values when a delimiter is provided.'
);

assertConfigValue(
    'PressDoWiki',
    Config::get('wiki.site_name', ' '),
    'Config::get should leave scalar values unchanged.'
);

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE config (`key` TEXT NOT NULL UNIQUE, `value` TEXT NOT NULL)');
    $pdo->exec("INSERT INTO config (`key`, `value`) VALUES ('existing', 'kept')");
    setConfigDb($pdo);

    Config::setBulk(['wiki.site_name', 'PressDoWiki', 'wiki.front_page', 'Frontpage']);
    $rows = $pdo->query("SELECT `key`, `value` FROM config ORDER BY `key`")->fetchAll(PDO::FETCH_KEY_PAIR);
    assertConfigValue(
        ['wiki.front_page' => 'Frontpage', 'wiki.site_name' => 'PressDoWiki'],
        $rows,
        'Config::setBulk should replace config rows atomically on success.'
    );

    try {
        Config::setBulk(['broken', 'one', 'broken', 'two']);
        fwrite(STDERR, 'Config::setBulk should throw on a failed insert.'.PHP_EOL);
        exit(1);
    } catch (Throwable) {
    }

    $rows = $pdo->query("SELECT `key`, `value` FROM config ORDER BY `key`")->fetchAll(PDO::FETCH_KEY_PAIR);
    assertConfigValue(
        ['wiki.front_page' => 'Frontpage', 'wiki.site_name' => 'PressDoWiki'],
        $rows,
        'Config::setBulk should roll back to previous rows when insert fails.'
    );

    $pdo->exec("INSERT INTO config (`key`, `value`) VALUES ('aclgroup.1.style', 'color: gray')");
    Config::replaceValues(['wiki.site_name' => 'Changed', 'wiki.timezone' => 'Asia/Seoul']);
    $rows = $pdo->query("SELECT `key`, `value` FROM config ORDER BY `key`")->fetchAll(PDO::FETCH_KEY_PAIR);
    assertConfigValue(
        [
            'aclgroup.1.style' => 'color: gray',
            'wiki.front_page' => 'Frontpage',
            'wiki.site_name' => 'Changed',
            'wiki.timezone' => 'Asia/Seoul',
        ],
        $rows,
        'Config::replaceValues should preserve settings outside the administrator catalog.'
    );
} else {
    fwrite(STDOUT, "Config setBulk transaction tests skipped: pdo_sqlite is unavailable.".PHP_EOL);
}

echo "Config tests passed.".PHP_EOL;
