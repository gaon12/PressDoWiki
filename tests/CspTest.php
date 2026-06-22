<?php

require __DIR__.'/../App/Helpers/Csp.php';

use PressDo\App\Helpers\Csp;

$header = Csp::headerValue();

$required = [
    "default-src 'self'",
    "script-src 'self'",
    'cdn.jsdelivr.net',
    'cdnjs.cloudflare.com',
    'challenges.cloudflare.com',
    'style-src',
    'font-src',
];

foreach ($required as $needle) {
    if (!str_contains($header, $needle)) {
        fwrite(STDERR, "CSP is missing expected source or directive: {$needle}".PHP_EOL);
        exit(1);
    }
}

if (!str_ends_with($header, ';')) {
    fwrite(STDERR, 'CSP header should end with a semicolon.'.PHP_EOL);
    exit(1);
}

echo "CSP tests passed.".PHP_EOL;
