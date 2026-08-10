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
    public function __construct(private InlineRenderer $inlineRenderer) {}

    /**
     * @param list<string> $lines
     */
    public function render(array $lines): string
    {
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
                    $this->inlineRenderer->render($heading[2]),
                    $level,
                );
                continue;
            }

            $paragraph[] = $line;
        }

        $this->flushParagraph($paragraph, $blocks);

        return implode("\n", $blocks);
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

        $renderedLines = array_map(
            fn(string $line): string => $this->inlineRenderer->render($line),
            $paragraph,
        );
        $blocks[] = '<p>' . implode("<br>\n", $renderedLines) . '</p>';
        $paragraph = [];
    }
}
