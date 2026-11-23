<?php

declare(strict_types=1);

namespace PressDo\App\Helpers;

final class Hangul
{
    private const FIRST_SYLLABLE = 0xAC00;
    private const LAST_SYLLABLE = 0xD7A3;
    private const INITIALS = ['ㄱ', 'ㄲ', 'ㄴ', 'ㄷ', 'ㄸ', 'ㄹ', 'ㅁ', 'ㅂ', 'ㅃ', 'ㅅ', 'ㅆ', 'ㅇ', 'ㅈ', 'ㅉ', 'ㅊ', 'ㅋ', 'ㅌ', 'ㅍ', 'ㅎ'];
    private const FINALS = ['', 'ㄱ', 'ㄲ', 'ㄳ', 'ㄴ', 'ㄵ', 'ㄶ', 'ㄷ', 'ㄹ', 'ㄺ', 'ㄻ', 'ㄼ', 'ㄽ', 'ㄾ', 'ㄿ', 'ㅀ', 'ㅁ', 'ㅂ', 'ㅄ', 'ㅅ', 'ㅆ', 'ㅇ', 'ㅈ', 'ㅊ', 'ㅋ', 'ㅌ', 'ㅍ', 'ㅎ'];

    public static function isHangul(string $character): bool
    {
        $codepoint = mb_ord($character, 'UTF-8');

        return ($codepoint >= 0x1100 && $codepoint <= 0x11FF)
            || ($codepoint >= 0x3130 && $codepoint <= 0x318F)
            || ($codepoint >= self::FIRST_SYLLABLE && $codepoint <= self::LAST_SYLLABLE);
    }

    public static function initial(string $character): string
    {
        $offset = self::syllableOffset($character);

        return $offset === null ? $character : self::INITIALS[intdiv($offset, 588)];
    }

    public static function finalConsonant(string $character): string
    {
        $offset = self::syllableOffset($character);

        return $offset === null ? '' : self::FINALS[$offset % 28];
    }

    public static function withoutFinalConsonant(string $character): string
    {
        $offset = self::syllableOffset($character);
        if ($offset === null) {
            return $character;
        }

        return mb_chr(self::FIRST_SYLLABLE + $offset - ($offset % 28), 'UTF-8');
    }

    private static function syllableOffset(string $character): ?int
    {
        $codepoint = mb_ord($character, 'UTF-8');

        return $codepoint >= self::FIRST_SYLLABLE && $codepoint <= self::LAST_SYLLABLE
            ? $codepoint - self::FIRST_SYLLABLE
            : null;
    }
}
