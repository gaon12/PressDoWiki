<?php

declare(strict_types=1);

namespace PressDo\App\Infrastructure\Rendering;

use PressDo\App\Helpers\Languages;

final class ViewTime
{
    public static function absolute(int $timestamp): string
    {
        return self::element($timestamp, date('Y-m-d H:i:s', $timestamp));
    }

    public static function relative(int $timestamp): string
    {
        $elapsed = time() - $timestamp;
        if ($elapsed < 10) {
            $label = Languages::get('recent', 'rightbefore');

            return is_string($label) ? self::element($timestamp, $label) : self::absolute($timestamp);
        }

        [$divisor, $unit] = match (true) {
            $elapsed < 60 => [1, 'second'],
            $elapsed < 3_600 => [60, 'minute'],
            $elapsed < 86_400 => [3_600, 'hour'],
            $elapsed < 2_592_000 => [86_400, 'day'],
            default => [0, ''],
        };
        if ($divisor === 0) {
            return self::absolute($timestamp);
        }

        $format = Languages::get('recent', 'before');
        $unitLabel = Languages::get('acl', $unit);

        return is_string($format) && is_string($unitLabel)
            ? self::element($timestamp, sprintf($format, intdiv($elapsed, $divisor), $unitLabel))
            : self::absolute($timestamp);
    }

    private static function element(int $timestamp, string $label): string
    {
        return '<time datetime="' . gmdate('Y-m-d\TH:i:s', $timestamp) . '.000Z">'
            . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</time>';
    }
}
