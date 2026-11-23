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
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'self'",
    "worker-src 'self' blob:",
    'style-src',
    'font-src',
];

foreach ($required as $needle) {
    if (!str_contains($header, $needle)) {
        fwrite(STDERR, "CSP is missing expected source or directive: {$needle}".PHP_EOL);
        exit(1);
    }
}

$directives = [];
foreach (explode('; ', rtrim($header, ';')) as $directive) {
    [$name, $sources] = explode(' ', $directive, 2);
    $directives[$name] = explode(' ', $sources);
}

if (in_array("'unsafe-inline'", $directives['script-src'], true)
    || isset($directives['child-src'])
    || in_array('*', $directives['frame-src'], true)
    || in_array('*', $directives['media-src'], true)
    || !in_array('https://*.hcaptcha.com', $directives['frame-src'], true)
    || !in_array('https://www.google.com/recaptcha/', $directives['frame-src'], true)) {
    fwrite(STDERR, 'The CSP must restrict executable content and frames.' . PHP_EOL);
    exit(1);
}

if (!str_ends_with($header, ';')) {
    fwrite(STDERR, 'CSP header should end with a semicolon.'.PHP_EOL);
    exit(1);
}

echo "CSP tests passed.".PHP_EOL;
