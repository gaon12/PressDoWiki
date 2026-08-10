<?php

declare(strict_types=1);

namespace PressDo\App\Services\Document;

use PDO;
use Throwable;

/**
 * Appends a revision and invalidates every parser-derived index atomically.
 *
 * Backlink refresh is the existing durable invalidation flag. The next wiki
 * render uses it to rebuild both backlinks and the full-text search row.
 */
final readonly class PdoDocumentRevisionStore
{
    public function __construct(private PDO $database) {}

    public function append(DocumentRevision $revision): void
    {
        $startedTransaction = !$this->database->inTransaction();
        if ($startedTransaction) {
            $this->database->beginTransaction();
        }

        try {
            $insert = $this->database->prepare(
                'INSERT INTO `history` (`uuid`, `document`, `content`, `comment`, `action`, `rev`, `count`, `contributor_m`, `contributor_i`, `edit_request_uri`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            );
            $insert->execute([
                $revision->revisionId,
                $revision->documentId,
                $revision->content,
                $revision->comment,
                $revision->action,
                $revision->revision,
                $revision->lengthDelta,
                $revision->contributorMemberId,
                $revision->contributorIpId,
                $revision->editRequestSlug,
            ]);

            $invalidate = $this->database->prepare(
                "UPDATE `document` SET `backlink_updated`='0' WHERE `uuid`=?",
            );
            $invalidate->execute([$revision->documentId]);

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

    /** Preserve the original database error if a driver already ended the transaction. */
    private function rollBackIfActive(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }
}
