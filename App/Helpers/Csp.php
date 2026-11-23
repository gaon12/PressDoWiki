<?php

namespace PressDo\App\Helpers;

final class Csp
{
    private const KATEX_DIST = 'https://cdn.jsdelivr.net/npm/katex@0.18.1/dist/';

    private const DIRECTIVES = [
        'default-src' => ["'self'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
        'frame-ancestors' => ["'self'"],
        'object-src' => ["'none'"],
        'img-src' => [
            "'self'",
            '*.theseed.io',
            'secure.gravatar.com',
            'www.google-analytics.com',
            'http://tn-skr2.smilevideo.jp',
            'data:',
        ],
        'media-src' => ["'self'", 'https:', 'data:'],
        'frame-src' => [
            "'self'",
            'https://www.google.com/recaptcha/',
            'https://recaptcha.google.com/recaptcha/',
            'https://challenges.cloudflare.com',
            'https://hcaptcha.com',
            'https://*.hcaptcha.com',
        ],
        'worker-src' => ["'self'"],
        'script-src' => [
            "'self'",
            'https://www.google.com/recaptcha/',
            'https://www.gstatic.com/recaptcha/',
            'www.googletagmanager.com',
            'www.google-analytics.com',
            self::KATEX_DIST . 'katex.min.js',
            self::KATEX_DIST . 'contrib/auto-render.min.js',
            'challenges.cloudflare.com',
            'https://hcaptcha.com',
            'https://*.hcaptcha.com',
        ],
        'style-src' => [
            "'self'",
            "'unsafe-inline'",
            'fonts.googleapis.com',
            self::KATEX_DIST . 'katex.min.css',
            'https://hcaptcha.com',
            'https://*.hcaptcha.com',
        ],
        'connect-src' => [
            "'self'",
            'www.google-analytics.com',
            'https://www.google.com/recaptcha/',
            'https://hcaptcha.com',
            'https://*.hcaptcha.com',
        ],
        'font-src' => [
            "'self'",
            'fonts.gstatic.com',
            self::KATEX_DIST . 'fonts/',
            'data:',
        ],
    ];

    public static function headerValue(): string
    {
        $directives = [];
        foreach (self::DIRECTIVES as $name => $sources) {
            $directives[] = $name . ' ' . implode(' ', array_unique($sources));
        }

        return implode('; ', $directives) . ';';
    }
}
