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
    [
        'username' => 'alice',
        'remember' => '1',
        'json' => '{"ok":true}',
        'settings' => ['wiki.site_name' => 'PressDo'],
    ],
    ['token' => 'cookie-value'],
    ['REMOTE_ADDR' => '127.0.0.1']
);

assertRequestValue('/w/Home', $request->queryString('redirect'), 'Request should read query strings.');
assertRequestValue('/w/Home', $request->queryOptionalString('redirect'), 'Request should read optional query strings.');
assertRequestValue(null, $request->queryOptionalString('missing'), 'Missing optional query strings should return null.');
assertRequestValue('', $request->queryString('nested'), 'Request should not coerce arrays into strings.');
assertRequestValue(null, $request->queryOptionalString('nested'), 'Optional query arrays should return null.');
assertRequestValue('alice', $request->postString('username'), 'Request should read POST strings.');
assertRequestValue(true, $request->hasPost('remember'), 'Request should report present POST keys.');
assertRequestValue('cookie-value', $request->cookieString('token'), 'Request should read cookie strings.');
assertRequestValue('127.0.0.1', $request->serverString('REMOTE_ADDR'), 'Request should read server strings.');
assertRequestValue(['ok' => true], $request->postJson('json'), 'Request should decode JSON POST values.');
assertRequestValue(null, $request->postJson('missing'), 'Request should return null for missing JSON POST values.');
assertRequestValue(
    ['wiki.site_name' => 'PressDo'],
    $request->postArray('settings'),
    'Request should expose string-keyed POST objects without using the superglobal.',
);
assertRequestValue(
    [
        'username' => 'alice',
        'remember' => '1',
        'json' => '{"ok":true}',
        'settings' => ['wiki.site_name' => 'PressDo'],
    ],
    $request->postData(),
    'Request should expose its normalized POST data to the view boundary.',
);

echo "Request tests passed.".PHP_EOL;
