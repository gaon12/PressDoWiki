<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use PressDo\App\Services\Uploaders\ObjectStorageInterface;
use Throwable;

/** Coordinates an external object write with the atomic file-document transaction. */
final readonly class FileUploadService
{
    public function __construct(
        private ObjectStorageInterface $storage,
        private PdoFileDocumentStore $documents,
    ) {}

    public function upload(PendingFileUpload $upload): void
    {
        if ($this->documents->hasDigest($upload->metadata->sha256)) {
            throw new DuplicateFileException('The same file binary is already registered.');
        }

        $stored = $this->storage->store($upload->objectKey, $upload->sourcePath);

        try {
            $this->documents->create(
                $upload->namespace,
                $upload->title,
                $upload->revision,
                $upload->metadata,
            );
        } catch (Throwable $persistenceFailure) {
            if (!$stored->created) {
                throw $persistenceFailure;
            }

            try {
                // A concurrent request may have committed the same digest after
                // this request created the shared content-addressed object. In
                // that case the winner now owns it and cleanup must not delete it.
                // This lookup makes cleanup best-effort: the database and object
                // store cannot share a transaction, so a later reconciliation job
                // must still treat recently written objects as potentially live.
                if (!$this->documents->hasDigest($upload->metadata->sha256)) {
                    $this->storage->delete($stored->key);
                }
            } catch (Throwable $compensationFailure) {
                throw new FileUploadCompensationException($persistenceFailure, $compensationFailure);
            }

            throw $persistenceFailure;
        }
    }
}
