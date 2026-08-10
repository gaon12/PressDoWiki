<?php

declare(strict_types=1);

namespace PressDo\App\Services\Search;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

/**
 * Converts rendered wiki HTML into bounded plain text for full-text indexes.
 *
 * Search indexes should contain visible document content, not executable
 * elements, table-of-contents duplicates, edit controls, or CSS source.
 */
final class SearchTextExtractor
{
    public const MAX_TEXT_BYTES = 2_097_152;

    private const BLOCK_ELEMENTS = [
        'address', 'article', 'aside', 'blockquote', 'br', 'dd', 'div', 'dl', 'dt',
        'figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'header', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'table',
        'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul',
    ];

    private const EXCLUDED_ELEMENTS = ['head', 'noscript', 'script', 'style', 'template'];

    public function extract(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorMode = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }

        if (!$loaded) {
            throw new RuntimeException('Rendered wiki HTML could not be parsed for search indexing.');
        }

        $body = $document->getElementsByTagName('body')->item(0);
        if (!$body instanceof DOMElement) {
            throw new RuntimeException('Rendered wiki HTML did not produce a document body.');
        }

        $text = $this->nodeText($body);
        $normalized = preg_replace('/[\p{Z}\s]+/u', ' ', $text);
        if ($normalized === null) {
            throw new RuntimeException('Search index text contains invalid UTF-8.');
        }

        $normalized = trim($normalized);
        if (strlen($normalized) > self::MAX_TEXT_BYTES) {
            $normalized = rtrim(mb_strcut($normalized, 0, self::MAX_TEXT_BYTES, 'UTF-8'));
        }

        return $normalized;
    }

    private function nodeText(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return $node->nodeValue ?? '';
        }

        $isBlock = false;
        if ($node instanceof DOMElement) {
            $tag = strtolower($node->tagName);
            if (
                in_array($tag, self::EXCLUDED_ELEMENTS, true)
                || $node->getAttribute('id') === 'toc'
                || $this->hasClass($node, 'wiki-macro-toc')
                || $this->hasClass($node, 'wiki-edit-section')
            ) {
                return '';
            }

            $isBlock = in_array($tag, self::BLOCK_ELEMENTS, true);
        }

        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= $this->nodeText($child);
        }

        return $isBlock ? ' ' . $text . ' ' : $text;
    }

    private function hasClass(DOMElement $element, string $className): bool
    {
        return preg_match(
            '/(?:\A|\s)' . preg_quote($className, '/') . '(?=\s|\z)/',
            $element->getAttribute('class'),
        ) === 1;
    }
}
