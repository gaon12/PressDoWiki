<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark\NamuMark;

use RuntimeException;

/**
 * Recognizes and renders the deliberately small, safe core of NamuMark tables.
 *
 * Table attributes and cell merging are separate grammar features. Treating
 * them as ordinary cell text until they have their own validator prevents CSS
 * or HTML from crossing the renderer's escaping boundary by accident.
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
            foreach ($cells as $cell) {
                $html .= '<td>' . $this->inlineRenderer->render(trim($cell)) . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table></div>';
    }

    private function reserveCells(int $count): void
    {
        if ($this->cellCount + $count > self::MAX_CELLS_PER_DOCUMENT) {
            throw new RuntimeException('NamuMark documents cannot contain more than 10,000 table cells.');
        }

        $this->cellCount += $count;
    }
}
