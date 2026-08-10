<?php

declare(strict_types=1);

namespace PressDo\App\Services\Document;

use InvalidArgumentException;

/** Immutable data required to append one document revision. */
final readonly class DocumentRevision
{
    public function __construct(
        public string $revisionId,
        public string $documentId,
        public ?string $content,
        public string $comment,
        public string $action,
        public int $revision,
        public int $lengthDelta,
        public ?string $contributorMemberId = null,
        public ?string $contributorIpId = null,
        public ?string $editRequestSlug = null,
        public ?string $movedFrom = null,
        public ?string $movedTo = null,
    ) {
        self::validateBinaryId($revisionId, 'revision');
        self::validateBinaryId($documentId, 'document');

        if ($action === '' || strlen($action) > 64) {
            throw new InvalidArgumentException('A document revision action must contain between 1 and 64 bytes.');
        }
        if ($revision < 1) {
            throw new InvalidArgumentException('A document revision number must be positive.');
        }
        if ($contributorMemberId !== null && $contributorIpId !== null) {
            throw new InvalidArgumentException('A revision cannot have both member and IP contributors.');
        }
        if ($contributorMemberId !== null) {
            self::validateBinaryId($contributorMemberId, 'member contributor');
        }
        if ($contributorIpId !== null) {
            self::validateBinaryId($contributorIpId, 'IP contributor');
        }
        if ($editRequestSlug !== null && ($editRequestSlug === '' || strlen($editRequestSlug) > 64)) {
            throw new InvalidArgumentException('An edit request slug must contain between 1 and 64 bytes.');
        }

        $hasMoveSource = $movedFrom !== null;
        $hasMoveDestination = $movedTo !== null;
        if ($hasMoveSource !== $hasMoveDestination || ($hasMoveSource && $action !== 'move')) {
            throw new InvalidArgumentException('Move provenance requires both titles and a move action.');
        }
        if ($action === 'move' && !$hasMoveSource) {
            throw new InvalidArgumentException('A move revision must contain its source and destination titles.');
        }
        if (
            ($movedFrom !== null && ($movedFrom === '' || mb_strlen($movedFrom, 'UTF-8') > 1024))
            || ($movedTo !== null && ($movedTo === '' || mb_strlen($movedTo, 'UTF-8') > 1024))
        ) {
            throw new InvalidArgumentException('Move titles must contain between 1 and 1024 characters.');
        }
    }

    private static function validateBinaryId(string $id, string $field): void
    {
        if (strlen($id) !== 16) {
            throw new InvalidArgumentException("A {$field} ID must contain exactly 16 bytes.");
        }
    }
}
