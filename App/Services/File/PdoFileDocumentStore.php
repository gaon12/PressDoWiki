<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use InvalidArgumentException;
use PDO;
use PDOException;
use PressDo\App\Services\Document\DocumentRevision;
use PressDo\App\Services\Document\PdoDocumentContentStore;
use Throwable;

/** Persists a file document, initial revision, and binary metadata atomically. */
final readonly class PdoFileDocumentStore
{
    public function __construct(private PDO $database) {}

    public function create(
        string $namespace,
        string $title,
        DocumentRevision $revision,
        FileMetadata $metadata,
    ): void {
        if ($metadata->documentId !== $revision->documentId) {
            throw new InvalidArgumentException('File metadata and its document revision must use the same ID.');
        }

        $startedTransaction = !$this->database->inTransaction();
        if ($startedTransaction) {
            $this->database->beginTransaction();
        }

        try {
            (new PdoDocumentContentStore($this->database))->create($namespace, $title, $revision);

            $insert = $this->database->prepare(
                'INSERT INTO files (uuid, hash, width, height) VALUES (?, ?, ?, ?)',
            );
            try {
                $insert->execute([
                    $metadata->documentId,
                    $metadata->sha256,
                    $metadata->width,
                    $metadata->height,
                ]);
            } catch (PDOException $error) {
                if ($this->isDuplicateDigest($error)) {
                    throw new DuplicateFileException('The same file binary is already registered.', previous: $error);
                }

                throw $error;
            }

            if ($startedTransaction) {
                $this->database->commit();
            }
        } catch (Throwable $error) {
            if ($startedTransaction) {
                $this->rollBackIfActive();
            }

            throw $error;
        }
    }

    public function hasDigest(string $sha256): bool
    {
        if (strlen($sha256) !== 32) {
            throw new InvalidArgumentException('A file SHA-256 digest must contain exactly 32 bytes.');
        }

        $select = $this->database->prepare('SELECT 1 FROM files WHERE hash=?');
        $select->execute([$sha256]);

        return $select->fetchColumn() !== false;
    }

    private function isDuplicateDigest(PDOException $error): bool
    {
        $driver = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!is_string($driver)) {
            return false;
        }

        $errorInfo = $error->errorInfo;
        $driverCode = is_array($errorInfo) ? ($errorInfo[1] ?? null) : null;

        return match (strtolower($driver)) {
            'mysql' => $driverCode === 1062 || $driverCode === '1062',
            'pgsql' => $error->getCode() === '23505',
            'sqlite' => str_contains($error->getMessage(), 'UNIQUE constraint failed: files.hash'),
            default => false,
        };
    }

    /** Preserve the original persistence error if the driver ended its transaction. */
    private function rollBackIfActive(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }
}
