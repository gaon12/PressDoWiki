<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use Closure;
use PDO;
use PressDo\App\Services\Uploaders\ObjectKey;
use RuntimeException;
use Throwable;

/** Serializes upload and maintenance mutations for one object key. */
final readonly class PdoObjectMutationLock implements ObjectMutationLockInterface
{
    public function __construct(private PDO $database) {}

    public function synchronized(ObjectKey $key, Closure $operation): mixed
    {
        $driver = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!is_string($driver)) {
            throw new RuntimeException('The database driver name is unavailable.');
        }

        return match (strtolower($driver)) {
            'sqlite' => $this->sqlite($operation),
            'mysql' => $this->advisory($key, $operation, true),
            'pgsql' => $this->advisory($key, $operation, false),
            default => throw new RuntimeException("Object mutation locks do not support database driver: {$driver}"),
        };
    }

    private function sqlite(Closure $operation): mixed
    {
        if ($this->database->inTransaction()) {
            throw new RuntimeException('SQLite object mutation locks require control of the outer transaction.');
        }

        $this->database->beginTransaction();
        try {
            // PDO cannot represent BEGIN IMMEDIATE in its transaction state.
            // A no-op write keeps PDO and nested repositories transaction-aware
            // while still acquiring SQLite's database-wide reserved write lock.
            $this->database->exec('UPDATE files SET hash=hash WHERE 0');
            $result = $operation();
            $this->database->commit();

            return $result;
        } catch (Throwable $operationError) {
            try {
                $this->rollBackSqliteIfActive();
            } catch (Throwable $rollbackError) {
                throw new RuntimeException(
                    'The object operation and SQLite rollback both failed: ' . $rollbackError->getMessage(),
                    previous: $operationError,
                );
            }

            throw $operationError;
        }
    }

    /** Preserve the operation error if SQLite already ended its transaction. */
    private function rollBackSqliteIfActive(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }

    private function advisory(ObjectKey $key, Closure $operation, bool $mysql): mixed
    {
        $name = 'pressdo-object:' . substr(hash('sha256', $key->value), 0, 40);
        $acquireSql = $mysql
            ? 'SELECT GET_LOCK(?, 10)'
            : 'SELECT pg_advisory_lock(hashtextextended(?, 0))';
        $releaseSql = $mysql
            ? 'SELECT RELEASE_LOCK(?)'
            : 'SELECT pg_advisory_unlock(hashtextextended(?, 0))';

        $acquire = $this->database->prepare($acquireSql);
        $acquire->execute([$name]);
        $acquired = $acquire->fetchColumn();
        if ($mysql && $acquired !== 1 && $acquired !== '1') {
            throw new RuntimeException('Timed out while acquiring the object mutation lock.');
        }

        try {
            $result = $operation();
        } catch (Throwable $operationError) {
            try {
                $this->releaseAdvisory($releaseSql, $name);
            } catch (Throwable $releaseError) {
                throw new RuntimeException(
                    'The object operation and advisory lock release both failed: ' . $releaseError->getMessage(),
                    previous: $operationError,
                );
            }

            throw $operationError;
        }

        $this->releaseAdvisory($releaseSql, $name);

        return $result;
    }

    private function releaseAdvisory(string $sql, string $name): void
    {
        $release = $this->database->prepare($sql);
        $release->execute([$name]);
    }
}
