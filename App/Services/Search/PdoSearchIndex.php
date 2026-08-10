<?php

declare(strict_types=1);

namespace PressDo\App\Services\Search;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Stores one current full-text index row per document. */
final readonly class PdoSearchIndex
{
    public function __construct(private PDO $database) {}

    public function put(string $documentId, string $text): void
    {
        if (strlen($documentId) !== 16) {
            throw new InvalidArgumentException('A search index document ID must contain exactly 16 bytes.');
        }

        $driverName = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!is_string($driverName) || $driverName === '') {
            throw new RuntimeException('The search index database driver could not be identified.');
        }

        $driver = strtolower($driverName);
        $sql = match ($driver) {
            'mysql' => 'INSERT INTO `search_index` (`document`, `text`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `text`=VALUES(`text`)',
            'pgsql' => 'INSERT INTO search_index (document, text) VALUES (?, ?) ON CONFLICT (document) DO UPDATE SET text=EXCLUDED.text',
            'sqlite' => 'INSERT INTO search_index (`document`, `text`) VALUES (?, ?) ON CONFLICT (`document`) DO UPDATE SET `text`=excluded.`text`',
            default => throw new RuntimeException("Unsupported search index database driver: {$driver}"),
        };

        $statement = $this->database->prepare($sql);
        $statement->execute([$documentId, $text]);
    }
}
