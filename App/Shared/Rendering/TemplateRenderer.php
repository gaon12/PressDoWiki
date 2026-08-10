<?php

declare(strict_types=1);

namespace PressDo\App\Shared\Rendering;

/**
 * Renders an application template without exposing the template engine.
 *
 * Controllers and use cases depend on this contract instead of Illuminate.
 * That keeps Blade replaceable and prevents framework objects from leaking into
 * wiki behavior.
 */
interface TemplateRenderer
{
    /**
     * @param array<string, mixed> $data Values made available to the template.
     */
    public function render(string $view, array $data = []): string;
}
