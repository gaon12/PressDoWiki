<?php

declare(strict_types=1);

namespace PressDo\App\Infrastructure\Files;

interface AuditLoggerInterface
{
    /** @param array<string, bool|float|int|string|null> $context */
    public function record(string $event, array $context): void;
}
