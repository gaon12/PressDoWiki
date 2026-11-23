<?php

declare(strict_types=1);

use PressDo\App\Http\Security\LoginChallenge;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function expectLoginChallenge(bool $passed, string $failure): void
{
    if (!$passed) {
        throw new RuntimeException($failure);
    }
}

$session = [];
$now = 1000;
expectLoginChallenge(!LoginChallenge::canAttempt($session, $now), 'A user must have an issued second-factor challenge.');
LoginChallenge::begin($session, $now);
expectLoginChallenge(LoginChallenge::canAttempt($session, $now + 599), 'The challenge should work before its deadline.');
expectLoginChallenge(!LoginChallenge::canAttempt($session, $now + 600), 'A challenge should expire at the deadline.');
for ($attempt = 1; $attempt <= 5; ++$attempt) {
    expectLoginChallenge(
        LoginChallenge::recordFailure($session, $now) === ($attempt < 5),
        'Failed attempts should be capped at five.',
    );
}
expectLoginChallenge(!LoginChallenge::canAttempt($session, $now), 'A capped challenge cannot be retried.');
LoginChallenge::begin($session, $now + 30);
expectLoginChallenge(LoginChallenge::canAttempt($session, $now + 31), 'A fresh password verification should start a fresh challenge.');

echo 'Login challenge tests passed.' . PHP_EOL;
