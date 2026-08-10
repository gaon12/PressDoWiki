<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use InvalidArgumentException;
use PDO;
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
            $insert->execute([
                $metadata->documentId,
                $metadata->sha256,
                $metadata->width,
                $metadata->height,
            ]);

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

    /** Preserve the original persistence error if the driver ended its transaction. */
    private function rollBackIfActive(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }
}
