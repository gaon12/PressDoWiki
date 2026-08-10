<?php

declare(strict_types=1);

use PressDo\App\Http\Security\LocalRedirect;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function assertLocalRedirect(string $expected, string $target, string $message, string $fallback = '/'): void
{
    $actual = LocalRedirect::sanitize($target, $fallback);
    if ($actual !== $expected) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

assertLocalRedirect('/w/Front_Page', '/w/Front_Page', 'A local path should be accepted.');
assertLocalRedirect('/member/mypage?tab=security#keys', '/member/mypage?tab=security#keys', 'A local query and fragment should be accepted.');
assertLocalRedirect('/', 'https://attacker.example', 'An absolute external URL should be rejected.');
assertLocalRedirect('/', '//attacker.example/path', 'A protocol-relative URL should be rejected.');
assertLocalRedirect('/', '/%2f%2fattacker.example/path', 'An encoded protocol-relative URL should be rejected.');
assertLocalRedirect('/', '/\\attacker.example/path', 'A backslash authority marker should be rejected.');
assertLocalRedirect('/', "/safe\r\nLocation: https://attacker.example", 'Header control characters should be rejected.');
assertLocalRedirect('/', 'relative/path', 'A relative path should be rejected.');
assertLocalRedirect('/safe', 'https://attacker.example', 'A valid local fallback should be preserved.', '/safe');

echo 'Local redirect tests passed.' . PHP_EOL;
