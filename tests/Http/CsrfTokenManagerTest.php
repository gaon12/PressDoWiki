<?php

declare(strict_types=1);

use PressDo\App\Http\Security\CsrfTokenManager;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failCsrfTokenManagerTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$manager = new CsrfTokenManager();
$session = [];
$token = $manager->issue($session, 'uploadtoken');
if (strlen($token) !== 64 || !ctype_xdigit($token) || $manager->issue($session, 'uploadtoken') !== $token) {
    failCsrfTokenManagerTest('A form token should be a stable 256-bit hexadecimal session secret.');
}
if (!$manager->validate($session, 'uploadtoken', $token)) {
    failCsrfTokenManagerTest('The exact session token should validate.');
}
foreach ([null, '', [], str_repeat('0', 64)] as $forgedToken) {
    if ($manager->validate($session, 'uploadtoken', $forgedToken)) {
        failCsrfTokenManagerTest('Missing, malformed, and forged CSRF tokens must fail validation.');
    }
}
$manager->consume($session, 'uploadtoken');
if ($manager->validate($session, 'uploadtoken', $token)) {
    failCsrfTokenManagerTest('A consumed token must not be reusable.');
}

echo 'CSRF token manager tests passed.' . PHP_EOL;
