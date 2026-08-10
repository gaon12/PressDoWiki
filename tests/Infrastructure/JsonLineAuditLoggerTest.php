<?php

declare(strict_types=1);

use PressDo\App\Infrastructure\Files\JsonLineAuditLogger;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failJsonLineAuditLoggerTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$directory = sys_get_temp_dir() . '/pressdo-audit-test-' . bin2hex(random_bytes(8));
$path = $directory . '/nested/maintenance.jsonl';
$logger = new JsonLineAuditLogger($path);

try {
    $logger->record('storage.cleanup.authorized', [
        'actor' => "관리자\n<script>alert(1)</script>",
        'size' => 42,
    ]);
    $logger->record('storage.cleanup.completed', ['deleted' => true]);

    $contents = file_get_contents($path);
    if ($contents === false) {
        failJsonLineAuditLoggerTest('The audit log should be readable after a successful append.');
    }

    $lines = explode("\n", rtrim($contents, "\n"));
    if (count($lines) !== 2) {
        failJsonLineAuditLoggerTest('Each audit event should occupy exactly one JSON line.');
    }

    $first = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
    $second = json_decode($lines[1], true, flags: JSON_THROW_ON_ERROR);
    if (
        !is_array($first)
        || !is_array($second)
        || ($first['event'] ?? null) !== 'storage.cleanup.authorized'
        || ($first['context']['actor'] ?? null) !== "관리자\n<script>alert(1)</script>"
        || ($second['event'] ?? null) !== 'storage.cleanup.completed'
        || !is_string($first['timestamp'] ?? null)
    ) {
        failJsonLineAuditLoggerTest('Audit events should preserve their structured timestamp, name, and context.');
    }
} finally {
    if (is_file($path)) {
        unlink($path);
    }
    if (is_dir($directory . '/nested')) {
        rmdir($directory . '/nested');
    }
    if (is_dir($directory)) {
        rmdir($directory);
    }
}

echo 'JSON line audit logger tests passed.' . PHP_EOL;
