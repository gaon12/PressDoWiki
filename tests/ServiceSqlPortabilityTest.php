<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function failServiceSqlPortabilityTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$serviceFiles = [
    'App/Services/Backlink/PdoBacklinkIndex.php',
    'App/Services/Document/PdoDocumentContentStore.php',
    'App/Services/Document/PdoDocumentDeletionStore.php',
    'App/Services/Document/PdoDocumentMoveStore.php',
    'App/Services/Document/PdoDocumentRevisionStore.php',
    'App/Services/File/PdoFileDocumentStore.php',
    'App/Services/File/PdoFileIntegrityRepository.php',
    'App/Services/File/PdoObjectReferenceRepository.php',
];

foreach ($serviceFiles as $relativePath) {
    $path = dirname(__DIR__) . '/' . $relativePath;
    $source = file_get_contents($path);
    if ($source === false) {
        failServiceSqlPortabilityTest("Service source could not be read: {$relativePath}");
    }

    // Backticks are identifier quotes only in MySQL and SQLite. PostgreSQL
    // interprets them as invalid syntax, so shared service SQL uses portable,
    // schema-controlled identifiers without vendor-specific quoting.
    if (str_contains($source, '`')) {
        failServiceSqlPortabilityTest("Shared service SQL contains a MySQL-only backtick: {$relativePath}");
    }

    foreach (['INSERT IGNORE', 'ON DUPLICATE KEY', 'REPLACE INTO'] as $mysqlOnlySyntax) {
        if (stripos($source, $mysqlOnlySyntax) !== false) {
            failServiceSqlPortabilityTest(
                "Shared service SQL contains MySQL-only syntax '{$mysqlOnlySyntax}': {$relativePath}",
            );
        }
    }
}

echo 'Shared service SQL portability tests passed.' . PHP_EOL;
