<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark;

use RuntimeException;

/**
 * Validated output shared by every markup engine and engine consumer.
 */
final readonly class MarkupResult
{
    public function __construct(
        public string $html,
        public MarkupLinks $links,
        public string $editorComment = '',
    ) {}

    /** @param array<string, mixed> $result */
    public static function fromLoaderResult(array $result): self
    {
        $html = $result['html'] ?? null;
        if (!is_string($html)) {
            throw new RuntimeException('Markup loaders must return an HTML string.');
        }

        $editorComment = $result['editor_comment'] ?? '';
        if (!is_string($editorComment)) {
            throw new RuntimeException('Markup loader editor_comment must be a string.');
        }

        return new self(
            $html,
            MarkupLinks::fromLoaderValue($result['links'] ?? [], $result['categories'] ?? []),
            $editorComment,
        );
    }
}
