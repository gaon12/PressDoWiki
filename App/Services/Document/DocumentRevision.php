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
    }

    private static function validateBinaryId(string $id, string $field): void
    {
        if (strlen($id) !== 16) {
            throw new InvalidArgumentException("A {$field} ID must contain exactly 16 bytes.");
        }
    }
}
