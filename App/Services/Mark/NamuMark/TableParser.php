<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark\NamuMark;

use RuntimeException;

/**
 * Renders NamuMark table cells and bounded numeric spans.
 */
final class TableParser
{
    private const MAX_COLUMNS_PER_ROW = 128;

    private const MAX_CELLS_PER_DOCUMENT = 10_000;

    private int $cellCount = 0;

    public function __construct(private readonly InlineRenderer $inlineRenderer) {}

    /**
     * A basic row starts and ends with two pipes. A malformed row returns null
     * so the block parser keeps the original source visible as escaped text.
     *
     * @return non-empty-list<string>|null
     */
    public function parseRow(string $line): ?array
    {
        if (strlen($line) < 4 || !str_starts_with($line, '||') || !str_ends_with($line, '||')) {
            return null;
        }

        $cells = explode('||', substr($line, 2, -2));
        if (count($cells) > self::MAX_COLUMNS_PER_ROW) {
            throw new RuntimeException('NamuMark table rows cannot contain more than 128 cells.');
        }

        return $cells;
    }

    /**
     * @param non-empty-list<non-empty-list<string>> $rows
     */
    public function render(array $rows): string
    {
        $html = '<div class="wiki-table-wrap"><table class="wiki-table"><tbody>';

        foreach ($rows as $cells) {
            $this->reserveCells(count($cells));
            $html .= '<tr>';
            $columnCount = 0;
            $implicitColspan = 1;
            foreach ($cells as $index => $cell) {
                if ($cell === '' && isset($cells[$index + 1])) {
                    ++$implicitColspan;
                    continue;
                }

                [$content, $colspan, $rowspan] = $this->parseCell($cell, $implicitColspan);
                $columnCount += $colspan;
                if ($columnCount > self::MAX_COLUMNS_PER_ROW) {
                    throw new RuntimeException('NamuMark table rows cannot contain more than 128 columns.');
                }

                $html .= '<td'
                    . ($colspan > 1 ? ' colspan="' . $colspan . '"' : '')
                    . ($rowspan > 1 ? ' rowspan="' . $rowspan . '"' : '')
                    . '>' . $this->inlineRenderer->render($content) . '</td>';
                $implicitColspan = 1;
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table></div>';
    }

    /** @return array{string, int, int} */
    private function parseCell(string $cell, int $implicitColspan): array
    {
        $content = trim($cell);
        $spans = [];
        for ($index = 0; $index < 2; ++$index) {
            if (preg_match('/\A<([-|])([1-9][0-9]{0,2})>/', $content, $match) !== 1
                || isset($spans[$match[1]])) {
                break;
            }

            $span = (int) $match[2];
            if ($span > self::MAX_COLUMNS_PER_ROW) {
                break;
            }

            $spans[$match[1]] = $span;
            $content = ltrim(substr($content, strlen($match[0])));
        }

        return [$content, $spans['-'] ?? $implicitColspan, $spans['|'] ?? 1];
    }

    private function reserveCells(int $count): void
    {
        if ($this->cellCount + $count > self::MAX_CELLS_PER_DOCUMENT) {
            throw new RuntimeException('NamuMark documents cannot contain more than 10,000 table cells.');
        }

        $this->cellCount += $count;
    }
}
