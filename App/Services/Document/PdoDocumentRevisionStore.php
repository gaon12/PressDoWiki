<?php

declare(strict_types=1);

namespace PressDo\App\Services\Document;

use InvalidArgumentException;
use PDO;
use PressDo\App\Helpers\SqlDialect;
use Throwable;

/**
 * Appends document revisions within the caller's transaction boundary.
 *
 * Content revisions invalidate parser-derived indexes for the next wiki
 * render. Metadata-only revisions, such as moves, retain those indexes because
 * the parsed content and every outgoing relationship remain unchanged.
 */
final readonly class PdoDocumentRevisionStore
{
    public function __construct(private PDO $database) {}

    public function append(DocumentRevision $revision): void
    {
        $this->persist($revision, true);
    }

    /** Append a metadata-only revision without rebuilding content-derived indexes. */
    public function appendMetadata(DocumentRevision $revision): void
    {
        if ($revision->content !== null) {
            throw new InvalidArgumentException('A metadata-only revision cannot contain document content.');
        }

        $this->persist($revision, false);
    }

    private function persist(DocumentRevision $revision, bool $invalidateDerivedIndexes): void
    {
        $startedTransaction = !$this->database->inTransaction();
        if ($startedTransaction) {
            $this->database->beginTransaction();
        }

        try {
            $insert = $this->database->prepare(
                'INSERT INTO history (uuid, document, content, comment, action, rev, count, contributor_m, contributor_i, edit_request_uri, moved_from, moved_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            );
            $values = [
                3 => $revision->content,
                $revision->comment,
                $revision->action,
                $revision->revision,
                $revision->lengthDelta,
                10 => $revision->editRequestSlug,
                $revision->movedFrom,
                $revision->movedTo,
            ];
            foreach ($values as $position => $value) {
                $insert->bindValue($position, $value);
            }
            SqlDialect::bindBinary($this->database, $insert, 1, $revision->revisionId);
            SqlDialect::bindBinary($this->database, $insert, 2, $revision->documentId);
            SqlDialect::bindBinary($this->database, $insert, 8, $revision->contributorMemberId);
            SqlDialect::bindBinary($this->database, $insert, 9, $revision->contributorIpId);
            $insert->execute();

            if ($invalidateDerivedIndexes) {
                $invalidate = $this->database->prepare(
                    "UPDATE document SET backlink_updated='0' WHERE uuid=?",
                );
                SqlDialect::bindBinary($this->database, $invalidate, 1, $revision->documentId);
                $invalidate->execute();
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

    /** Preserve the original database error if a driver already ended the transaction. */
    private function rollBackIfActive(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }
}
