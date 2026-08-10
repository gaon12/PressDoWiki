<?php

declare(strict_types=1);

namespace PressDo\App\Services\Document;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/** Atomically soft-deletes a document and removes indexes derived from its content. */
final readonly class PdoDocumentDeletionStore
{
    public function __construct(private PDO $database) {}

    public function delete(DocumentRevision $revision): void
    {
        if ($revision->action !== 'delete' || $revision->content !== null || $revision->lengthDelta > 0) {
            throw new InvalidArgumentException('A deletion revision must have no content and a non-positive length delta.');
        }

        $startedTransaction = !$this->database->inTransaction();
        if ($startedTransaction) {
            $this->database->beginTransaction();
        }

        try {
            $markDeleted = $this->database->prepare(
                "UPDATE document SET status='delete' WHERE uuid=? AND status='normal'",
            );
            $markDeleted->execute([$revision->documentId]);
            if ($markDeleted->rowCount() !== 1) {
                throw new RuntimeException('Only an existing, normal document can be deleted.');
            }

            (new PdoDocumentRevisionStore($this->database))->append($revision);

            $deleteSearch = $this->database->prepare('DELETE FROM search_index WHERE document=?');
            $deleteSearch->execute([$revision->documentId]);

            // Incoming links belong to their source documents and remain useful
            // as references to a now-missing title. Only this document's outgoing
            // relationships cease to exist when its content is deleted.
            $deleteOutgoingLinks = $this->database->prepare('DELETE FROM links WHERE from_uuid=?');
            $deleteOutgoingLinks->execute([$revision->documentId]);

            $markIndexesSynchronized = $this->database->prepare(
                "UPDATE document SET backlink_updated='1' WHERE uuid=?",
            );
            $markIndexesSynchronized->execute([$revision->documentId]);

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

    /** Preserve the original mutation error if the driver already ended its transaction. */
    private function rollBackIfActive(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }
}
