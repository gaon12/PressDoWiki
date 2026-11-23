<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark\NamuMark;

use Closure;
use RuntimeException;

/** Collects a bounded set of footnotes for one rendered fragment. */
final class Footnotes
{
    /** @var array<int, array{label: string, html: string, reference: string}> */
    private array $pending = [];

    /** @var array<string, int> */
    private array $names = [];

    private int $noteCount = 0;
    private int $referenceCount = 0;

    public function __construct(private readonly string $prefix) {}

    /** @param Closure(string): string $renderBody */
    public function reference(string $source, Closure $renderBody): ?string
    {
        if (strlen($source) > 2_048
            || preg_match('/\A([^\s\[\]<>]{0,64})(?:[ \t]+(.*))?\z/u', $source, $match) !== 1) {
            return null;
        }

        $name = $match[1];
        $body = trim($match[2] ?? '');
        $noteId = $name !== '' ? ($this->names[$name] ?? null) : null;
        if ($noteId === null && $body === '') {
            return null;
        }

        $reference = $this->prefix . '-ref-' . ++$this->referenceCount;
        if ($noteId === null) {
            if ($this->noteCount >= 1_000) {
                throw new RuntimeException('NamuMark documents cannot contain more than 1,000 footnotes.');
            }

            $noteId = ++$this->noteCount;
            $this->pending[$noteId] = [
                'label' => self::escape($name !== '' ? $name : (string) $noteId),
                'html' => $renderBody($body),
                'reference' => $reference,
            ];
            if ($name !== '') {
                $this->names[$name] = $noteId;
            }
        }

        return sprintf(
            '<sup><a class="wiki-footnote-ref" id="%s" href="#%s">[%s]</a></sup>',
            $reference,
            $this->prefix . '-' . $noteId,
            self::escape($name !== '' ? $name : (string) $noteId),
        );
    }

    public function flush(): string
    {
        if ($this->pending === []) {
            return '';
        }

        $html = '<div class="wiki-macro-footnote">';
        foreach ($this->pending as $id => $note) {
            $html .= sprintf(
                '<span class="footnote-list" id="%s"><a href="#%s">[%s]</a> %s</span>',
                $this->prefix . '-' . $id,
                $note['reference'],
                $note['label'],
                $note['html'],
            );
        }
        $this->pending = [];

        return $html . '</div>';
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
