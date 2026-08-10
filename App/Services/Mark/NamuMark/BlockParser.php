<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark\NamuMark;

/**
 * Splits a document into block-level structures before inline rendering.
 *
 * Keeping block recognition here avoids a single regular expression growing
 * into an implicit framework as NamuMark compatibility expands.
 */
final readonly class BlockParser
{
    private const MAX_NESTING_DEPTH = 8;

    public function __construct(
        private InlineRenderer $inlineRenderer,
        private TableParser $tableParser,
        private LinkCollection $links,
    ) {}

    /**
     * @param list<string> $lines
     */
    public function render(array $lines): string
    {
        $writer = new SectionWriter();
        $paragraph = [];
        $headingNumber = 0;

        for ($index = 0, $lineCount = count($lines); $index < $lineCount; ++$index) {
            $line = $lines[$index];

            if (trim($line) === '') {
                $this->flushParagraph($paragraph, $writer);
                continue;
            }

            if ($index === 0 && ($redirect = $this->matchRedirect($line)) !== null) {
                $this->links->addRedirect($redirect);
                continue;
            }

            if (trim($line) === '{{{') {
                $closingIndex = $this->findLiteralBlockEnd($lines, $index + 1);
                if ($closingIndex === null) {
                    $this->flushParagraph($paragraph, $writer);
                    $literalLines = array_map(
                        fn(string $literalLine): string => $this->inlineRenderer->renderLiteral($literalLine),
                        array_slice($lines, $index),
                    );
                    $writer->addBlock('<p>' . implode("<br>\n", $literalLines) . '</p>');
                    break;
                }

                $this->flushParagraph($paragraph, $writer);
                $literal = implode("\n", array_slice($lines, $index + 1, $closingIndex - $index - 1));
                $writer->addBlock('<pre class="wiki-code"><code>'
                    . $this->inlineRenderer->renderLiteral($literal)
                    . '</code></pre>');
                $index = $closingIndex;
                continue;
            }

            if (preg_match('/\A(={1,6})[ \t]+(.+?)[ \t]+\1\z/u', $line, $heading) === 1) {
                $this->flushParagraph($paragraph, $writer);
                ++$headingNumber;
                $level = strlen($heading[1]);
                $writer->addHeading(
                    $level,
                    $headingNumber,
                    $this->inlineRenderer->render($heading[2]),
                );
                continue;
            }

            if (($tableRow = $this->tableParser->parseRow($line)) !== null) {
                $this->flushParagraph($paragraph, $writer);
                $tableRows = [$tableRow];

                while ($index + 1 < $lineCount) {
                    $nextRow = $this->tableParser->parseRow($lines[$index + 1]);
                    if ($nextRow === null) {
                        break;
                    }

                    $tableRows[] = $nextRow;
                    ++$index;
                }

                $writer->addBlock($this->tableParser->render($tableRows));
                continue;
            }

            if ($this->matchQuote($line) !== null) {
                $this->flushParagraph($paragraph, $writer);
                $quotes = [];
                while ($index < $lineCount && ($quote = $this->matchQuote($lines[$index])) !== null) {
                    $quotes[] = $quote;
                    ++$index;
                }
                --$index;
                $writer->addBlock($this->renderQuotes($quotes));
                continue;
            }

            if ($this->matchListItem($line) !== null) {
                $this->flushParagraph($paragraph, $writer);
                $items = [];
                while ($index < $lineCount && ($item = $this->matchListItem($lines[$index])) !== null) {
                    $items[] = $item;
                    ++$index;
                }
                --$index;
                $writer->addBlock($this->renderList($items));
                continue;
            }

            $paragraph[] = $line;
        }

        $this->flushParagraph($paragraph, $writer);

        return $writer->finish();
    }

    private function matchRedirect(string $line): ?string
    {
        if (preg_match('/\A#(?:redirect|넘겨주기)[ \t]+(.+?)\s*\z/iu', $line, $match) !== 1) {
            return null;
        }

        $target = trim($match[1]);
        if (
            $target === ''
            || strlen($target) > 255
            || preg_match('/[\x00-\x1F\x7F]/', $target) === 1
            || parse_url($target, PHP_URL_SCHEME) !== null
        ) {
            return null;
        }

        return $target;
    }

    /**
     * @param list<string> $lines
     */
    private function findLiteralBlockEnd(array $lines, int $startIndex): ?int
    {
        for ($index = $startIndex, $lineCount = count($lines); $index < $lineCount; ++$index) {
            if (trim($lines[$index]) === '}}}') {
                return $index;
            }
        }

        return null;
    }

    /** @return array{depth: int, text: string}|null */
    private function matchQuote(string $line): ?array
    {
        if (preg_match('/\A(>{1,' . self::MAX_NESTING_DEPTH . '})[ \t]?(.*)\z/u', $line, $match) !== 1) {
            return null;
        }

        return [
            'depth' => strlen($match[1]),
            'text' => $match[2],
        ];
    }

    /**
     * @param list<array{depth: int, text: string}> $quotes
     */
    private function renderQuotes(array $quotes): string
    {
        $rendered = [];
        foreach ($quotes as $quote) {
            $line = '<p>' . $this->inlineRenderer->render($quote['text']) . '</p>';
            for ($depth = 1; $depth < $quote['depth']; ++$depth) {
                $line = '<blockquote>' . $line . '</blockquote>';
            }
            $rendered[] = $line;
        }

        return '<blockquote class="wiki-quote">' . implode("\n", $rendered) . '</blockquote>';
    }

    /** @return array{depth: int, kind: ListKind, text: string}|null */
    private function matchListItem(string $line): ?array
    {
        if (
            preg_match(
                '/\A( {1,' . self::MAX_NESTING_DEPTH . '})(\*|1\.|a\.|A\.|i\.|I\.)[ \t]+(.*)\z/u',
                $line,
                $match,
            ) !== 1
        ) {
            return null;
        }

        return [
            'depth' => strlen($match[1]),
            'kind' => ListKind::from($match[2]),
            'text' => $match[3],
        ];
    }

    /**
     * @param list<array{depth: int, kind: ListKind, text: string}> $items
     */
    private function renderList(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $index = 0;

        $html = '';
        while ($index < count($items)) {
            $html .= $this->renderListLevel(
                $items,
                $index,
                $items[$index]['depth'],
                $items[$index]['kind'],
            );
        }

        return $html;
    }

    /**
     * @param list<array{depth: int, kind: ListKind, text: string}> $items
     */
    private function renderListLevel(array $items, int &$index, int $depth, ListKind $kind): string
    {
        $tag = $kind->tag();
        $html = '<' . $tag . ' class="' . $kind->cssClass() . '">';
        $itemCount = count($items);

        while ($index < $itemCount) {
            $item = $items[$index];
            if ($item['depth'] !== $depth || $item['kind'] !== $kind) {
                break;
            }

            ++$index;
            $html .= '<li>' . $this->inlineRenderer->render($item['text']);

            while ($index < $itemCount && $items[$index]['depth'] > $depth) {
                $html .= $this->renderListLevel(
                    $items,
                    $index,
                    $items[$index]['depth'],
                    $items[$index]['kind'],
                );
            }

            $html .= '</li>';
        }

        return $html . '</' . $tag . '>';
    }

    /**
     * @param list<string> $paragraph
     */
    private function flushParagraph(array &$paragraph, SectionWriter $writer): void
    {
        if ($paragraph === []) {
            return;
        }

        $renderedLines = array_map(
            fn(string $line): string => $this->inlineRenderer->render($line),
            $paragraph,
        );
        $writer->addBlock('<p>' . implode("<br>\n", $renderedLines) . '</p>');
        $paragraph = [];
    }
}
