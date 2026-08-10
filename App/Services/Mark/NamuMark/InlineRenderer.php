<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark\NamuMark;

/**
 * Renders inline syntax without ever parsing generated HTML as markup again.
 */
final readonly class InlineRenderer
{
    private const TOKEN_PATTERN = "~(\[\[[^\]\r\n]{1,2048}\]\]|'''[^\r\n]+?'''|''[^\r\n]+?''|__[^\r\n]+?__|\~\~[^\r\n]+?\~\~|--[^\r\n]+?--)~u";

    public function __construct(private LinkCollection $links) {}

    public function render(string $text): string
    {
        $segments = preg_split(
            self::TOKEN_PATTERN,
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        if ($segments === false) {
            return $this->escape($text);
        }

        $html = '';
        foreach ($segments as $segment) {
            $html .= preg_match(self::TOKEN_PATTERN, $segment) === 1
                ? $this->renderToken($segment)
                : $this->escape($segment);
        }

        return $html;
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
