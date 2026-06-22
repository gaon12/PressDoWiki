<?php
namespace PressDo\App\Helpers;

final class Csp
{
    private const DIRECTIVES = [
        'default-src' => ["'self'"],
        'img-src' => [
            "'self'",
            '*.theseed.io',
            'secure.gravatar.com',
            'www.google-analytics.com',
            'http://tn-skr2.smilevideo.jp',
            'data:',
        ],
        'media-src' => ['*'],
        'child-src' => ['*'],
        'script-src' => [
            "'self'",
            "'unsafe-eval'",
            "'unsafe-inline'",
            'www.google.com',
            'www.gstatic.com',
            'www.googletagmanager.com',
            'www.google-analytics.com',
            'unpkg.com',
            'cdn.jsdelivr.net',
            'cdnjs.cloudflare.com',
            'challenges.cloudflare.com',
            'js.hcaptcha.com',
        ],
        'style-src' => [
            "'self'",
            "'unsafe-inline'",
            'fonts.googleapis.com',
            'cdn.jsdelivr.net',
        ],
        'connect-src' => [
            "'self'",
            'www.google-analytics.com',
        ],
        'font-src' => [
            "'self'",
            'fonts.gstatic.com',
            'data:',
        ],
    ];

    public static function headerValue(): string
    {
        $directives = [];
        foreach (self::DIRECTIVES as $name => $sources) {
            $directives[] = $name.' '.implode(' ', array_unique($sources));
        }

        return implode('; ', $directives).';';
    }
}
