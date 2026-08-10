<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark\NamuMark;

final class Loader
{
    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function loadMarkUp(string $content, array $options): array
    {
        return (new Renderer())->render($content);
    }
}
