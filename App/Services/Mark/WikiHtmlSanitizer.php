<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class WikiHtmlSanitizer
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig())
                ->allowSafeElements()
                ->allowLinkSchemes(['http', 'https', 'mailto'])
                ->allowMediaSchemes(['http', 'https'])
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->dropAttribute('id', '*')
                ->dropAttribute('name', '*')
                ->dropAttribute('class', '*')
                ->dropElement('form')
                ->withMaxInputLength(MarkupResult::MAX_HTML_BYTES),
        );

        return self::$sanitizer->sanitize($html);
    }
}
