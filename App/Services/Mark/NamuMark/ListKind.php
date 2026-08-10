<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark\NamuMark;

/**
 * Supported public list markers and their semantic HTML representation.
 */
enum ListKind: string
{
    case Unordered = '*';
    case Decimal = '1.';
    case LowerAlpha = 'a.';
    case UpperAlpha = 'A.';
    case LowerRoman = 'i.';
    case UpperRoman = 'I.';

    public function tag(): string
    {
        return $this === self::Unordered ? 'ul' : 'ol';
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Unordered => 'wiki-list',
            self::Decimal => 'wiki-list wiki-list-decimal',
            self::LowerAlpha => 'wiki-list wiki-list-alpha',
            self::UpperAlpha => 'wiki-list wiki-list-upper-alpha',
            self::LowerRoman => 'wiki-list wiki-list-roman',
            self::UpperRoman => 'wiki-list wiki-list-upper-roman',
        };
    }
}
