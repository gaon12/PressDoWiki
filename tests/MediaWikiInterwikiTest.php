<?php

require __DIR__.'/../App/Services/Mark/MediaWiki/src/DefaultParserBackend.php';

$backend = new DefaultParserBackend();
$backend->loadInterwikiLinks();

$ref = new ReflectionProperty(DefaultParserBackend::class, 'interwiki');
$ref->setAccessible(true);
$interwiki = $ref->getValue($backend);

if (!is_array($interwiki)) {
    fwrite(STDERR, 'Interwiki map should load as an array.'.PHP_EOL);
    exit(1);
}

foreach ($interwiki as $prefix => $url) {
    if (!is_string($prefix) || !is_string($url)) {
        fwrite(STDERR, 'Interwiki map should contain only string prefix/url pairs.'.PHP_EOL);
        exit(1);
    }
}

echo "MediaWiki interwiki tests passed.".PHP_EOL;
