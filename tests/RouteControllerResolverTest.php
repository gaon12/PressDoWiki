<?php

require __DIR__.'/../App/Helpers/RouteControllerResolver.php';

use PressDo\App\Helpers\RouteControllerResolver;

function assertRoute(?string $expected, object $uriData, string $message): void
{
    $actual = RouteControllerResolver::resolve($uriData);
    if ($expected !== $actual) {
        fwrite(STDERR, $message.PHP_EOL);
        fwrite(STDERR, 'Expected: '.var_export($expected, true).PHP_EOL);
        fwrite(STDERR, 'Actual: '.var_export($actual, true).PHP_EOL);
        exit(1);
    }
}

assertRoute(
    'PressDo\\App\\Controllers\\Pages\\Wiki',
    (object) ['page' => 'wiki'],
    'Wiki page should resolve to the Wiki controller.'
);

assertRoute(
    'PressDo\\App\\Controllers\\Pages\\member\\RecoverPassword',
    (object) ['page' => 'member', 'menu' => 'recover_password'],
    'Member menu should resolve through the explicit allow list.'
);

assertRoute(
    'PressDo\\App\\Controllers\\Pages\\api\\Search',
    (object) ['page' => 'api', 'menu' => 'search'],
    'API menu should resolve through the explicit allow list.'
);

assertRoute(
    null,
    (object) ['page' => '..\\member', 'menu' => 'login'],
    'Unexpected page segments should not resolve to a controller.'
);

assertRoute(
    null,
    (object) ['page' => 'member', 'menu' => '..\\login'],
    'Unexpected grouped menu segments should not resolve to a controller.'
);

echo "Route controller resolver tests passed.".PHP_EOL;
