<?php

declare(strict_types=1);

namespace PressDo\App\Core;

/**
 * Immutable skin metadata selected for the current request.
 */
final readonly class Skin
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public string $name,
        public array $config,
    ) {}
}
