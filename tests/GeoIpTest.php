<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PressDo\App\Helpers\Config;
use PressDo\App\Helpers\GeoIp;

function assertGeoIpValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$config = new ReflectionProperty(Config::class, 'Configs');
$config->setValue(null, ['wiki.geoip2_database' => '']);

assertGeoIpValue(
    null,
    GeoIp::getTimezone('127.0.0.1'),
    'An unavailable GeoIP database should fall back to the site timezone.',
);
assertGeoIpValue(
    null,
    GeoIp::getTimezone('not-an-ip'),
    'An invalid client address should not stop application startup.',
);
assertGeoIpValue(
    'UNAVAILABLE',
    GeoIp::get('127.0.0.1'),
    'Country lookup should fail closed when GeoIP is not configured.',
);

try {
    GeoIp::get('not-an-ip');
    fwrite(STDERR, 'Country lookup should reject malformed addresses.' . PHP_EOL);
    exit(1);
} catch (ErrorException) {
}

echo 'GeoIP tests passed.' . PHP_EOL;
