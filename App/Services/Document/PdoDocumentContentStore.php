<?php

declare(strict_types=1);

namespace PressDo\App\Services\Document;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/** Creates or recreates a document together with its first visible content revision. */
final readonly class PdoDocumentContentStore
{
    public function __construct(private PDO $database) {}

    public function create(string $namespace, string $title, DocumentRevision $revision): void
    {
        $this->validateContentRevision($revision);
        if ($revision->revision !== 1) {
            throw new InvalidArgumentException('A new document must start at revision 1.');
        }
        $this->validateLocation($namespace, $title);

        $this->transactional(function () use ($namespace, $title, $revision): void {
            $create = $this->database->prepare(
                'INSERT INTO `document` (`uuid`, `namespace`, `title`) VALUES (?, ?, ?)',
            );
            $create->execute([$revision->documentId, $namespace, $title]);

            (new PdoDocumentRevisionStore($this->database))->append($revision);
        });
    }

    public function recreate(DocumentRevision $revision): void
    {
        $this->validateContentRevision($revision);
        if ($revision->revision < 2) {
            throw new InvalidArgumentException('A recreated document must continue an existing revision history.');
        }

        $this->transactional(function () use ($revision): void {
            $restore = $this->database->prepare(
                "UPDATE `document` SET `status`='normal' WHERE `uuid`=? AND `status`='delete'",
            );
            $restore->execute([$revision->documentId]);
            if ($restore->rowCount() !== 1) {
                throw new RuntimeException('Only a deleted document can be recreated.');
            }

            (new PdoDocumentRevisionStore($this->database))->append($revision);
        });
    }

    private function validateContentRevision(DocumentRevision $revision): void
    {
        if ($revision->action !== 'create' || $revision->content === null) {
            throw new InvalidArgumentException('Document creation requires a create revision with content.');
        }
    }

    private function validateLocation(string $namespace, string $title): void
    {
        if ($namespace === '' || mb_strlen($namespace, 'UTF-8') > 256) {
            throw new InvalidArgumentException('A document namespace must contain between 1 and 256 characters.');
        }
        if ($title === '' || mb_strlen($title, 'UTF-8') > 1024) {
            throw new InvalidArgumentException('A document title must contain between 1 and 1024 characters.');
        }
    }

    /** @param callable(): void $mutation */
    private function transactional(callable $mutation): void
    {
        $startedTransaction = !$this->database->inTransaction();
        if ($startedTransaction) {
            $this->database->beginTransaction();
        }

        try {
            $mutation();

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

    /** Preserve the original write error if the driver already ended its transaction. */
    private function rollBackIfActive(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }
}
