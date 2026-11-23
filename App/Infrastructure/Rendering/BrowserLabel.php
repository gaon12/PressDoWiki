<?php

declare(strict_types=1);

namespace PressDo\App\Infrastructure\Rendering;

final class BrowserLabel
{
    public static function describe(string $agent): string
    {
        $browser = match (true) {
            preg_match('~(?:Edg|EdgA|EdgiOS|Edge)/~', $agent) === 1 => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'SamsungBrowser/') => 'Samsung Internet',
            str_contains($agent, 'Firefox/') || str_contains($agent, 'FxiOS/') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => '',
        };
        $platform = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPad') || str_contains($agent, 'iPhone') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };

        return implode(' · ', array_filter([$browser, $platform]));
    }
}
