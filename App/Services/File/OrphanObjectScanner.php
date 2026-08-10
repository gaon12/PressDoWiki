<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use InvalidArgumentException;
use PressDo\App\Services\Uploaders\ObjectKey;
use PressDo\App\Services\Uploaders\ObjectStorageInterface;

/** Finds old unreferenced managed objects without deleting them. */
final readonly class OrphanObjectScanner
{
    public function __construct(
        private ObjectStorageInterface $storage,
        private PdoObjectReferenceRepository $references,
    ) {}

    public function scan(?ObjectKey $after, int $limit, int $now, int $graceSeconds = 86400): OrphanObjectReport
    {
        if ($graceSeconds < 3600) {
            throw new InvalidArgumentException('The orphan-object grace period must be at least one hour.');
        }

        $page = $this->storage->listObjects($after, $limit);
        $managed = [];
        $ignoredUnmanaged = 0;
        foreach ($page->items as $object) {
            if (FileObjectLocator::isManagedKey($object->key)) {
                $managed[] = $object->key;
            } else {
                ++$ignoredUnmanaged;
            }
        }
        $referenced = $this->references->referencedKeys($managed);

        $candidates = [];
        $ignoredRecent = 0;
        foreach ($page->items as $object) {
            if (!FileObjectLocator::isManagedKey($object->key) || isset($referenced[$object->key->value])) {
                continue;
            }
            $age = max(0, $now - $object->lastModified);
            if ($age < $graceSeconds) {
                ++$ignoredRecent;
                continue;
            }
            $candidates[] = new OrphanObjectCandidate($object, $age);
        }

        return new OrphanObjectReport(
            $candidates,
            count($page->items),
            $ignoredRecent,
            $ignoredUnmanaged,
            $page->nextCursor,
        );
    }
}
