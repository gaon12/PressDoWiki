<?php

declare(strict_types=1);

namespace PressDo\App\Http\Security;

use PDO;
use RuntimeException;

final readonly class LoginRateLimiter
{
    public function __construct(private PDO $database) {}

    /** Consume one attempt before doing the protected work. Return seconds until retry, or zero on success. */
    public function reserve(LoginRateScope $scope, string $identity, int $now): int
    {
        [$maximum, $window] = $scope->policy();
        $expires = (intdiv($now, $window) + 1) * $window;
        if (str_ends_with($scope->value, ':ip')) {
            $identity = inet_pton($identity) ?: $identity;
        }
        $key = hash('sha256', $scope->value . "\0" . $identity);

        $insert = 'INSERT INTO login_rate_limits (bucket_key, expires_at, attempts) VALUES (?, ?, 1)';
        $suffix = match ($this->database->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'mysql' => ' ON DUPLICATE KEY UPDATE attempts = IF(attempts < ?, attempts + 1, attempts)',
            'pgsql', 'sqlite' => ' ON CONFLICT (expires_at, bucket_key) DO UPDATE
                SET attempts = login_rate_limits.attempts + 1 WHERE login_rate_limits.attempts < ?',
            default => throw new RuntimeException('Unsupported login rate limit database driver.'),
        };
        $statement = $this->database->prepare($insert . $suffix);
        $statement->bindValue(1, $key);
        $statement->bindValue(2, $expires, PDO::PARAM_INT);
        $statement->bindValue(3, $maximum, PDO::PARAM_INT);
        $statement->execute();
        $reserved = $statement->rowCount() > 0;

        if (random_int(1, 128) === 1) {
            $this->database->prepare('DELETE FROM login_rate_limits WHERE expires_at <= ?')->execute([$now]);
        }

        return $reserved ? 0 : $expires - $now;
    }
}
