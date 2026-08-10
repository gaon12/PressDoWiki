<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark\NamuMark;

/**
 * Renders inline syntax without ever parsing generated HTML as markup again.
 */
final class InlineRenderer
{
    private const TOKEN_PATTERN = "~(\[\[[^\]\r\n]{1,2048}\]\]|'''[^\r\n]+?'''|''[^\r\n]+?''|__[^\r\n]+?__|\~\~[^\r\n]+?\~\~|--[^\r\n]+?--)~u";

    private const MAX_INLINE_TOKENS = 50_000;

    private const MAX_TOKEN_MARKERS = 100_000;

    private int $inlineTokens = 0;

    private int $tokenMarkers = 0;

    public function __construct(private readonly LinkCollection $links) {}

    public function render(string $text): string
    {
        $markers = substr_count($text, '[[')
            + substr_count($text, "''")
            + substr_count($text, '__')
            + substr_count($text, '~~')
            + substr_count($text, '--');
        if ($markers === 0) {
            return $this->escape($text);
        }

        $this->tokenMarkers += $markers;
        if ($this->tokenMarkers > self::MAX_TOKEN_MARKERS) {
            throw new \RuntimeException('NamuMark input exceeds the inline marker budget.');
        }

        $segments = preg_split(
            self::TOKEN_PATTERN,
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );

        if ($segments === false) {
            return $this->escape($text);
        }

        $this->inlineTokens += intdiv(count($segments), 2);
        if ($this->inlineTokens > self::MAX_INLINE_TOKENS) {
            throw new \RuntimeException('NamuMark input exceeds the 50,000 inline-token rendering limit.');
        }

        $html = '';
        foreach ($segments as $index => $segment) {
            $html .= $index % 2 === 1 ? $this->renderToken($segment) : $this->escape($segment);
        }

        return $html;
    }

    /**
     * Escapes literal block contents without applying any inline syntax.
     */
    public function renderLiteral(string $text): string
    {
        return $this->escape($text);
    }

    private function renderToken(string $token): string
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

        if (str_starts_with($target, '분류:')) {
            $category = trim(substr($target, strlen('분류:')));
            if ($category === '' || strlen($category) > 255) {
                return $this->escape($originalToken);
            }

            $this->links->addCategory($category);

            return '';
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
        if (strlen($document) > 255) {
            return $this->escape($originalToken);
        }
        $href = $document === '' ? '' : '/w/' . rawurlencode($document);
        if ($fragment !== null && $fragment !== '') {
            $href .= '#' . rawurlencode($fragment);
        }

        if ($href === '') {
            return $this->escape($originalToken);
        }

        if ($document !== '') {
            $this->links->addLink($document);
        }

        return sprintf(
            '<a class="wiki-link" href="%s">%s</a>',
            $this->escape($href),
            $this->escape($label),
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
