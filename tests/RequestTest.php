<?php

require __DIR__.'/../App/Core/Request.php';

use PressDo\App\Core\Request;

function assertRequestValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message.PHP_EOL);
        fwrite(STDERR, 'Expected: '.var_export($expected, true).PHP_EOL);
        fwrite(STDERR, 'Actual: '.var_export($actual, true).PHP_EOL);
        exit(1);
    }
}

$request = new Request(
    ['redirect' => '/w/Home', 'nested' => ['bad']],
    ['username' => 'alice', 'remember' => '1', 'json' => '{"ok":true}'],
    ['token' => 'cookie-value'],
    ['REMOTE_ADDR' => '127.0.0.1']
);

assertRequestValue('/w/Home', $request->queryString('redirect'), 'Request should read query strings.');
assertRequestValue('', $request->queryString('nested'), 'Request should not coerce arrays into strings.');
assertRequestValue('alice', $request->postString('username'), 'Request should read POST strings.');
assertRequestValue(true, $request->hasPost('remember'), 'Request should report present POST keys.');
assertRequestValue('cookie-value', $request->cookieString('token'), 'Request should read cookie strings.');
assertRequestValue('127.0.0.1', $request->serverString('REMOTE_ADDR'), 'Request should read server strings.');
assertRequestValue(['ok' => true], $request->postJson('json'), 'Request should decode JSON POST values.');
assertRequestValue(null, $request->postJson('missing'), 'Request should return null for missing JSON POST values.');

echo "Request tests passed.".PHP_EOL;
