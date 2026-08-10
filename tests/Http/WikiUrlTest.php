<?php

declare(strict_types=1);

use PressDo\App\Http\WikiUrl;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function assertWikiUrlValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

assertWikiUrlValue(
    '/w/%EB%8C%80%EC%83%81%20%EB%AC%B8%EC%84%9C%2F%ED%95%98%EC%9C%84?from=%EC%9B%90%EB%B3%B8%20%EB%AC%B8%EC%84%9C',
    WikiUrl::document('대상 문서/하위', ['from' => '원본 문서']),
    'Wiki URLs should encode document paths and query values independently.',
);

foreach (['', "title\r\nX-Test: injected", str_repeat('x', 1_025)] as $invalidTitle) {
    try {
        WikiUrl::document($invalidTitle);
        fwrite(STDERR, 'Unsafe wiki titles must be rejected before redirect construction.' . PHP_EOL);
        exit(1);
    } catch (InvalidArgumentException) {
    }
}

echo 'Wiki URL tests passed.' . PHP_EOL;
