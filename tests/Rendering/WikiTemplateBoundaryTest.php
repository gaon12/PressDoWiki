<?php

declare(strict_types=1);

$templatePath = dirname(__DIR__, 2) . '/App/Views/layouts/wiki.latte';
$template = file_get_contents($templatePath);
if ($template === false) {
    fwrite(STDERR, 'Unable to read the wiki layout template.' . PHP_EOL);
    exit(1);
}

if (str_contains($template, 'htmlspecialchars_decode')) {
    fwrite(STDERR, 'Rendered markup must not make an encode/decode round trip in the template.' . PHP_EOL);
    exit(1);
}

if (!str_contains($template, "['document']['content']|noescape")) {
    fwrite(STDERR, 'The wiki template should emit the already-rendered markup result once.' . PHP_EOL);
    exit(1);
}

echo 'Wiki template boundary tests passed.' . PHP_EOL;
