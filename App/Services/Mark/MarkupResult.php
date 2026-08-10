<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark;

use RuntimeException;

/**
 * Validated output shared by every markup engine and engine consumer.
 */
final readonly class MarkupResult
{
    private const MAX_HTML_BYTES = 8_388_608;

    private const MAX_EDITOR_COMMENT_BYTES = 65_536;

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
        if (strlen($html) > self::MAX_HTML_BYTES) {
            throw new RuntimeException('Markup loader HTML exceeds the 8 MiB output limit.');
        }

        $editorComment = $result['editor_comment'] ?? '';
        if (!is_string($editorComment)) {
            throw new RuntimeException('Markup loader editor_comment must be a string.');
        }
        if (strlen($editorComment) > self::MAX_EDITOR_COMMENT_BYTES) {
            throw new RuntimeException('Markup loader editor_comment exceeds the 64 KiB limit.');
        }

        return new self(
            $html,
            MarkupLinks::fromLoaderValue($result['links'] ?? [], $result['categories'] ?? []),
            $editorComment,
        );
    }
}
