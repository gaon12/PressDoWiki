<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark\Markdown;

use League\CommonMark\GithubFlavoredMarkdownConverter;

final class Loader
{
    /**
     * @param array<string, mixed> $options Reserved for the shared loader API.
     * @return array{html: string, categories: array<string, never>}
     */
    public static function loadMarkUp(string $content, array $options): array
    {
        $converter = new GithubFlavoredMarkdownConverter([
            // Wiki content is user-controlled. Preserve raw HTML as visible text
            // and remove unsafe link targets instead of trusting either one.
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        return [
            'html' => (string) $converter->convert($content),
            'categories' => [],
        ];
    }
}
