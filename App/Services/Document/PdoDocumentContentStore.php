<?php

declare(strict_types=1);

namespace PressDo\App\Services\Document;

use InvalidArgumentException;
use PDO;
use PDOException;
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
                'INSERT INTO document (uuid, namespace, title) VALUES (?, ?, ?)',
            );
            try {
                $create->execute([$revision->documentId, $namespace, $title]);
            } catch (PDOException $error) {
                if (str_starts_with((string) $error->getCode(), '23')) {
                    throw new DocumentConflictException(
                        'A document now exists at the requested location.',
                        previous: $error,
                    );
                }

                throw $error;
            }

            (new PdoDocumentRevisionStore($this->database))->append($revision);
        });
    }

    public function initialize(
        string $namespace,
        string $title,
        DocumentRevision $revision,
    ): void {
        $this->validateContentRevision($revision);
        if ($revision->revision !== 1) {
            throw new InvalidArgumentException('A placeholder document must start at revision 1.');
        }
        $this->validateLocation($namespace, $title);

        $this->transactional(function () use ($namespace, $title, $revision): void {
            $document = $this->lockDocument($revision->documentId);
            $this->assertDocumentState($document, $namespace, $title, 'normal');
            $this->assertLatestRevision($revision->documentId, 0);

            (new PdoDocumentRevisionStore($this->database))->append($revision);
        });
    }

    public function recreate(
        string $namespace,
        string $title,
        int $expectedBaseRevision,
        DocumentRevision $revision,
    ): void {
        $this->validateContentRevision($revision);
        $this->validateContinuation($revision, $expectedBaseRevision);
        $this->validateLocation($namespace, $title);

        $this->transactional(function () use ($namespace, $title, $expectedBaseRevision, $revision): void {
            $document = $this->lockDocument($revision->documentId);
            $this->assertDocumentState($document, $namespace, $title, 'delete');
            $this->assertLatestRevision($revision->documentId, $expectedBaseRevision);

            $restore = $this->database->prepare(
                "UPDATE document SET status='normal' WHERE uuid=? AND status='delete'",
            );
            $restore->execute([$revision->documentId]);
            if ($restore->rowCount() !== 1) {
                throw new DocumentConflictException('The deleted document changed before it could be recreated.');
            }

            (new PdoDocumentRevisionStore($this->database))->append($revision);
        });
    }

    public function edit(
        string $namespace,
        string $title,
        int $expectedBaseRevision,
        DocumentRevision $revision,
    ): void {
        if ($revision->action !== 'modify' || $revision->content === null) {
            throw new InvalidArgumentException('Document editing requires a modify revision with content.');
        }
        $this->validateContinuation($revision, $expectedBaseRevision);
        $this->validateLocation($namespace, $title);

        $this->transactional(function () use ($namespace, $title, $expectedBaseRevision, $revision): void {
            $document = $this->lockDocument($revision->documentId);
            $this->assertDocumentState($document, $namespace, $title, 'normal');
            $this->assertLatestRevision($revision->documentId, $expectedBaseRevision);

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

    private function validateContinuation(DocumentRevision $revision, int $expectedBaseRevision): void
    {
        if ($expectedBaseRevision < 1 || $revision->revision !== $expectedBaseRevision + 1) {
            throw new InvalidArgumentException('A continued document revision must immediately follow its base revision.');
        }
    }

    /** @return array{namespace: string, title: string, status: string} */
    private function lockDocument(string $documentId): array
    {
        $driver = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!is_string($driver) || $driver === '') {
            throw new RuntimeException('The document database driver could not be identified.');
        }

        if (strtolower($driver) === 'sqlite') {
            // SQLite has no SELECT ... FOR UPDATE. A harmless write acquires its
            // transaction write lock before the revision number is inspected.
            $lock = $this->database->prepare(
                'UPDATE document SET backlink_updated=backlink_updated WHERE uuid=?',
            );
            $lock->execute([$documentId]);
            $sql = 'SELECT namespace, title, status FROM document WHERE uuid=?';
        } elseif (in_array(strtolower($driver), ['mysql', 'pgsql'], true)) {
            $sql = 'SELECT namespace, title, status FROM document WHERE uuid=? FOR UPDATE';
        } else {
            throw new RuntimeException("Unsupported document database driver: {$driver}");
        }

        $select = $this->database->prepare($sql);
        $select->execute([$documentId]);
        $row = $select->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new DocumentConflictException('The document no longer exists.');
        }

        $namespace = $row['namespace'] ?? null;
        $title = $row['title'] ?? null;
        $status = $row['status'] ?? null;
        if (!is_string($namespace) || !is_string($title) || !is_string($status)) {
            throw new RuntimeException('The locked document row is malformed.');
        }

        return ['namespace' => $namespace, 'title' => $title, 'status' => $status];
    }

    /** @param array{namespace: string, title: string, status: string} $document */
    private function assertDocumentState(
        array $document,
        string $namespace,
        string $title,
        string $status,
    ): void {
        if (
            $document['namespace'] !== $namespace
            || $document['title'] !== $title
            || $document['status'] !== $status
        ) {
            throw new DocumentConflictException('The document location or status changed while it was being edited.');
        }
    }

    private function assertLatestRevision(string $documentId, int $expectedBaseRevision): void
    {
        $select = $this->database->prepare('SELECT MAX(rev) FROM history WHERE document=?');
        $select->execute([$documentId]);
        $latest = $select->fetchColumn();

        if ($latest === null || $latest === false) {
            $latestRevision = 0;
        } elseif (is_int($latest)) {
            $latestRevision = $latest;
        } elseif (preg_match('/\A\d+\z/D', $latest) === 1) {
            $latestRevision = (int) $latest;
        } else {
            throw new RuntimeException('The latest document revision number is malformed.');
        }

        if ($latestRevision !== $expectedBaseRevision) {
            throw new DocumentConflictException('A newer document revision already exists.');
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
