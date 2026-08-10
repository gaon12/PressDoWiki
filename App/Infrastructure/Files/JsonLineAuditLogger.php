<?php

declare(strict_types=1);

namespace PressDo\App\Infrastructure\Files;

use JsonException;
use RuntimeException;

/** Appends one locked JSON object per line for durable maintenance audits. */
final readonly class JsonLineAuditLogger implements AuditLoggerInterface
{
    public function __construct(private string $path) {}

    public function record(string $event, array $context): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('The audit log directory could not be created.');
        }

        try {
            $line = json_encode([
                'timestamp' => gmdate('c'),
                'event' => $event,
                'context' => $context,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        } catch (JsonException $error) {
            throw new RuntimeException('The audit event could not be encoded.', previous: $error);
        }

        $handle = @fopen($this->path, 'ab');
        if ($handle === false) {
            throw new RuntimeException('The audit log could not be opened.');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('The audit log could not be locked.');
            }

            $this->writeCompletely($handle, $line);
            if (!fflush($handle) || !fsync($handle)) {
                throw new RuntimeException('The audit event could not be flushed to disk.');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param resource $handle */
    private function writeCompletely(mixed $handle, string $line): void
    {
        $offset = 0;
        $length = strlen($line);
        while ($offset < $length) {
            $written = fwrite($handle, substr($line, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('The audit event could not be written completely.');
            }

            $offset += $written;
        }
    }
}
