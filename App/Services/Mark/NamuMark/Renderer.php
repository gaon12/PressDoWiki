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

    private const INLINE_PATTERN = "~(\[\[[^\]\r\n]{1,2048}\]\]|'''[^\r\n]+?'''|''[^\r\n]+?''|__[^\r\n]+?__|\~\~[^\r\n]+?\~\~|--[^\r\n]+?--)~u";

    /**
     * @var array{
     *     link: list<string>,
     *     redirect: list<string>,
     *     include: list<string>,
     *     file: list<string>,
     *     category: list<string>
     * }
     */
    private array $links = [
        'link' => [],
        'redirect' => [],
        'include' => [],
        'file' => [],
        'category' => [],
    ];

    /**
     * @return array{
     *     html: string,
     *     categories: list<string>,
     *     links: array{
     *         link: list<string>,
     *         redirect: list<string>,
     *         include: list<string>,
     *         file: list<string>,
     *         category: list<string>
     *     }
     * }
     */
    public function render(string $source): array
    {
        if (strlen($source) > self::MAX_INPUT_BYTES) {
            throw new RuntimeException('NamuMark input exceeds the 2 MiB rendering limit.');
        }

        $this->links = [
            'link' => [],
            'redirect' => [],
            'include' => [],
            'file' => [],
            'category' => [],
        ];

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $source));
        $blocks = [];
        $paragraph = [];
        $headingNumber = 0;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                $this->flushParagraph($paragraph, $blocks);
                continue;
            }

            if (preg_match('/\A(={1,6})[ \t]+(.+?)[ \t]+\1\z/u', $line, $heading) === 1) {
                $this->flushParagraph($paragraph, $blocks);
                ++$headingNumber;
                $level = strlen($heading[1]);
                $blocks[] = sprintf(
                    '<h%d id="s-%d">%s</h%d>',
                    $level,
                    $headingNumber,
                    $this->renderInline($heading[2]),
                    $level,
                );
                continue;
            }

            $paragraph[] = $line;
        }

        $this->flushParagraph($paragraph, $blocks);
        $this->deduplicateLinks();

        return [
            'html' => implode("\n", $blocks),
            'categories' => $this->links['category'],
            'links' => $this->links,
        ];
    }

    /**
     * @param list<string> $paragraph
     * @param list<string> $blocks
     */
    private function flushParagraph(array &$paragraph, array &$blocks): void
    {
        if ($paragraph === []) {
            return;
        }

        $renderedLines = array_map($this->renderInline(...), $paragraph);
        $blocks[] = '<p>' . implode("<br>\n", $renderedLines) . '</p>';
        $paragraph = [];
    }

    private function renderInline(string $text): string
    {
        $segments = preg_split(
            self::INLINE_PATTERN,
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        if ($segments === false) {
            return $this->escape($text);
        }

        $html = '';
        foreach ($segments as $segment) {
            $html .= preg_match(self::INLINE_PATTERN, $segment) === 1
                ? $this->renderInlineToken($segment)
                : $this->escape($segment);
        }

        return $html;
    }

    private function renderInlineToken(string $token): string
    {
        if (str_starts_with($token, '[[')) {
            return $this->renderLink(substr($token, 2, -2), $token);
        }

        if (str_starts_with($token, "'''")) {
            return '<strong>' . $this->escape(substr($token, 3, -3)) . '</strong>';
        }

        if (str_starts_with($token, "''")) {
            return '<em>' . $this->escape(substr($token, 2, -2)) . '</em>';
        }

        if (str_starts_with($token, '__')) {
            return '<u>' . $this->escape(substr($token, 2, -2)) . '</u>';
        }

        return '<del>' . $this->escape(substr($token, 2, -2)) . '</del>';
    }

    private function renderLink(string $linkSource, string $originalToken): string
    {
        $linkParts = explode('|', $linkSource, 2);
        $target = trim($linkParts[0]);
        $label = $linkParts[1] ?? null;
        $label = $label === null || trim($label) === '' ? $target : trim($label);

        if ($target === '' || preg_match('/[\x00-\x1F\x7F]/', $target) === 1) {
            return $this->escape($originalToken);
        }

        $url = parse_url($target);
        if (is_array($url) && isset($url['scheme'])) {
            if (
                !in_array($url['scheme'], ['http', 'https'], true)
                || !is_string($url['host'] ?? null)
                || isset($url['user'])
                || isset($url['pass'])
            ) {
                return $this->escape($originalToken);
            }

            return sprintf(
                '<a href="%s" rel="nofollow noopener noreferrer">%s</a>',
                $this->escape($target),
                $this->escape($label),
            );
        }

        $targetParts = explode('#', $target, 2);
        $document = $targetParts[0];
        $fragment = $targetParts[1] ?? null;
        $href = $document === '' ? '' : '/w/' . rawurlencode($document);
        if ($fragment !== null && $fragment !== '') {
            $href .= '#' . rawurlencode($fragment);
        }

        if ($href === '') {
            return $this->escape($originalToken);
        }

        if ($document !== '') {
            $this->links['link'][] = $document;
        }

        return sprintf(
            '<a class="wiki-link" href="%s">%s</a>',
            $this->escape($href),
            $this->escape($label),
        );
    }

    private function deduplicateLinks(): void
    {
        foreach ($this->links as $type => $targets) {
            $this->links[$type] = array_values(array_unique($targets));
        }
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
