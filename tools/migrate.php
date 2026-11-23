<?php

declare(strict_types=1);

use PressDo\App\Helpers\Database;
use PressDo\App\Infrastructure\Database\SchemaMigrator;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $applied = (new SchemaMigrator(Database::getInstance()))->migrate();
    echo ($applied === [] ? 'Database is up to date.' : 'Applied database migrations ' . implode(', ', $applied) . '.') . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Database migration failed. ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
