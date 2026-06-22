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

echo "Config tests passed.".PHP_EOL;
