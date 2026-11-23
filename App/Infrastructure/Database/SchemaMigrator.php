<?php

declare(strict_types=1);

namespace PressDo\App\Infrastructure\Database;

use LogicException;
use PDO;
use RuntimeException;

final readonly class SchemaMigrator
{
    // Keep published migrations immutable. Each statement must tolerate a retry
    // after DDL has committed but before its version was recorded.
    private const MIGRATIONS = [
        1 => [
            'CREATE TABLE IF NOT EXISTS login_rate_limits (
                bucket_key CHAR(64) NOT NULL,
                expires_at BIGINT NOT NULL,
                attempts INTEGER NOT NULL,
                PRIMARY KEY (expires_at, bucket_key)
            )',
        ],
    ];

    public function __construct(private PDO $database) {}

    /** @return list<int> */
    public function migrate(): array
    {
        if ($this->database->inTransaction()) {
            throw new LogicException('Schema migrations require a dedicated connection with no active transaction.');
        }

        $driver = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException('Schema migrations support MariaDB/MySQL, PostgreSQL and SQLite.');
        }

        // Avoid partially initializing an empty or incorrectly selected database.
        $this->database->query('SELECT 1 FROM member WHERE 1 = 0');
        $engine = $driver === 'mysql' ? ' ENGINE=InnoDB' : '';
        $this->database->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version INTEGER PRIMARY KEY,
            applied_at BIGINT NOT NULL
        )' . $engine);

        $exists = $this->database->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
        $record = $this->database->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)');
        $applied = [];
        foreach (self::MIGRATIONS as $version => $statements) {
            $exists->execute([$version]);
            $registered = $exists->fetchColumn() !== false;
            $exists->closeCursor();
            if ($registered) {
                continue;
            }

            foreach ($statements as $statement) {
                $this->database->exec($statement . $engine);
            }
            $record->execute([$version, time()]);
            $applied[] = $version;
        }

        return $applied;
    }
}
