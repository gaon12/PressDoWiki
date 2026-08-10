<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use InvalidArgumentException;
use PressDo\App\Infrastructure\Files\AuditLoggerInterface;
use PressDo\App\Services\Uploaders\ObjectInfo;
use PressDo\App\Services\Uploaders\ObjectKey;
use PressDo\App\Services\Uploaders\ObjectStorageInterface;
use Throwable;

/** Revalidates and deletes one old orphan while uploads for that key are blocked. */
final readonly class OrphanObjectCleaner
{
    public function __construct(
        private ObjectStorageInterface $storage,
        private PdoObjectReferenceRepository $references,
        private ObjectMutationLockInterface $lock,
        private AuditLoggerInterface $audit,
        private int $graceSeconds = 86400,
    ) {
        if ($graceSeconds < 3600) {
            throw new InvalidArgumentException('The orphan-object grace period must be at least one hour.');
        }
    }

    public function delete(ObjectKey $key, int $expectedLastModified, int $now, string $actor): ObjectInfo
    {
        if ($expectedLastModified < 0 || $now < 0 || trim($actor) === '') {
            throw new InvalidArgumentException('Cleanup confirmation metadata and actor are required.');
        }

        return $this->lock->synchronized(
            $key,
            fn(): ObjectInfo => $this->deleteLocked($key, $expectedLastModified, $now, $actor),
        );
    }

    private function deleteLocked(ObjectKey $key, int $expectedLastModified, int $now, string $actor): ObjectInfo
    {
        if (!FileObjectLocator::isManagedKey($key)) {
            throw new OrphanObjectCleanupException('Only managed file objects can be removed by orphan cleanup.');
        }

        $object = $this->storage->metadata($key);
        if ($object === null) {
            throw new OrphanObjectCleanupException('The object no longer exists. Refresh the integrity report.');
        }
        if ($object->lastModified !== $expectedLastModified) {
            throw new OrphanObjectCleanupException('The object changed after the report was loaded. Refresh and review it again.');
        }

        $age = max(0, $now - $object->lastModified);
        if ($age < $this->graceSeconds) {
            throw new OrphanObjectCleanupException('The object is still inside the cleanup grace period.');
        }
        if (isset($this->references->referencedKeys([$key])[$key->value])) {
            throw new OrphanObjectCleanupException('The object is now referenced by file metadata and cannot be removed.');
        }

        $context = [
            'actor' => $actor,
            'object_key' => $key->value,
            'last_modified' => $object->lastModified,
            'size' => $object->size,
        ];

        // Authorization must be durable before the irreversible storage call.
        $this->audit->record('storage.orphan_delete_authorized', $context);
        try {
            $this->storage->delete($key);
        } catch (Throwable $deleteError) {
            try {
                $this->audit->record('storage.orphan_delete_failed', $context + [
                    'error' => $deleteError->getMessage(),
                ]);
            } catch (Throwable $auditError) {
                throw new OrphanObjectCleanupException(
                    'Object deletion failed and its failure audit could not be written: ' . $auditError->getMessage(),
                    previous: $deleteError,
                );
            }

            throw new OrphanObjectCleanupException('Object deletion failed.', previous: $deleteError);
        }

        try {
            $this->audit->record('storage.orphan_deleted', $context);
        } catch (Throwable $auditError) {
            throw new OrphanObjectCleanupException(
                'The object was deleted, but its completion audit could not be written.',
                previous: $auditError,
            );
        }

        return $object;
    }
}
