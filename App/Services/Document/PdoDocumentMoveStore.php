<?php

declare(strict_types=1);

namespace PressDo\App\Services\Document;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/** Atomically changes a document title and appends its metadata-only move revision. */
final readonly class PdoDocumentMoveStore
{
    public function __construct(private PDO $database) {}

    public function move(
        DocumentRevision $revision,
        string $sourceNamespace,
        string $sourceTitle,
        string $destinationNamespace,
        string $destinationTitle,
    ): void {
        if ($revision->action !== 'move' || $revision->content !== null || $revision->lengthDelta !== 0) {
            throw new InvalidArgumentException('A move revision must be content-free with a zero length delta.');
        }
        foreach ([$sourceNamespace, $sourceTitle, $destinationNamespace, $destinationTitle] as $part) {
            if ($part === '' || mb_strlen($part, 'UTF-8') > 1024) {
                throw new InvalidArgumentException('Document title parts must contain between 1 and 1024 characters.');
            }
        }
        if ($sourceNamespace === $destinationNamespace && $sourceTitle === $destinationTitle) {
            throw new InvalidArgumentException('A document move must change its namespace or title.');
        }

        $startedTransaction = !$this->database->inTransaction();
        if ($startedTransaction) {
            $this->database->beginTransaction();
        }

        try {
            $move = $this->database->prepare(
                "UPDATE `document` SET `namespace`=?, `title`=? WHERE `uuid`=? AND `namespace`=? AND `title`=? AND `status`='normal'",
            );
            $move->execute([
                $destinationNamespace,
                $destinationTitle,
                $revision->documentId,
                $sourceNamespace,
                $sourceTitle,
            ]);
            if ($move->rowCount() !== 1) {
                throw new RuntimeException('The document no longer exists at the expected source title.');
            }

            // A title change does not alter parsed content, so the existing
            // search text and outgoing relationships remain current.
            (new PdoDocumentRevisionStore($this->database))->appendMetadata($revision);

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

    /** Preserve the original move error if the driver already ended its transaction. */
    private function rollBackIfActive(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }
}
