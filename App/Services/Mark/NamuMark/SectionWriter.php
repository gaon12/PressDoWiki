<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark\NamuMark;

/**
 * Builds the heading hierarchy after individual blocks have been recognized.
 *
 * A heading's content wrapper is kept immediately after the heading element.
 * The bundled document script relies on that small DOM contract to fold a
 * section, while nesting the wrappers makes folding a parent include all of
 * its child sections.
 */
final class SectionWriter
{
    /** @var list<string> */
    private array $fragments = [];

    /** @var list<int> */
    private array $openLevels = [];

    public function addBlock(string $html): void
    {
        $this->fragments[] = $html;
    }

    public function addHeading(int $level, int $number, string $titleHtml): void
    {
        $this->closeSectionsAtOrBelow($level);

        $id = 's-' . $number;
        $this->fragments[] = sprintf(
            '<h%d class="wiki-heading" id="%s">%s</h%d>'
                . '<div class="wiki-heading-content" aria-labelledby="%s">',
            $level,
            $id,
            $titleHtml,
            $level,
            $id,
        );
        $this->openLevels[] = $level;
    }

    public function finish(): string
    {
        while ($this->openLevels !== []) {
            array_pop($this->openLevels);
            $this->fragments[] = '</div>';
        }

        return implode("\n", $this->fragments);
    }

    private function closeSectionsAtOrBelow(int $nextLevel): void
    {
        while ($this->openLevels !== []) {
            $currentLevel = $this->openLevels[array_key_last($this->openLevels)];
            if ($currentLevel < $nextLevel) {
                return;
            }

            array_pop($this->openLevels);
            $this->fragments[] = '</div>';
        }
    }
}
