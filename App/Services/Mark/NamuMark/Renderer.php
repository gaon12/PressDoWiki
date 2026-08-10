<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark\NamuMark;

use RuntimeException;

/**
 * A clean-room NamuMark renderer built from public syntax behaviour.
 *
 * This first compatibility slice intentionally supports a small, safe core.
 * Unsupported syntax stays visible as text instead of being interpreted as HTML.
 */
final class Renderer
{
    private const MAX_INPUT_BYTES = 2_097_152;

    private const MAX_INPUT_LINES = 100_000;

    /**
     * @return array{
     *     html: string,
     *     categories: array<string, list<string>>,
     *     links: array{
     *         link: list<string>,
     *         redirect: list<string>,
     *         include: list<string>,
     *         file: list<string>,
     *         category: array<string, list<string>>
     *     }
     * }
     */
    public function render(string $source): array
    {
        if (strlen($source) > self::MAX_INPUT_BYTES) {
            throw new RuntimeException('NamuMark input exceeds the 2 MiB rendering limit.');
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $source));
        if (count($lines) > self::MAX_INPUT_LINES) {
            throw new RuntimeException('NamuMark input exceeds the 100,000-line rendering limit.');
        }

        $links = new LinkCollection();
        $inlineRenderer = new InlineRenderer($links);
        $html = (new BlockParser($inlineRenderer, new TableParser($inlineRenderer), $links))->render($lines);
        $collectedLinks = $links->all();

        return [
            'html' => $html,
            'categories' => $collectedLinks['category'],
            'links' => $collectedLinks,
        ];
    }
}
