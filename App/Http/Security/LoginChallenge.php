<?php

declare(strict_types=1);

namespace PressDo\App\Http\Security;

final class LoginChallenge
{
    private const LIFETIME = 600;
    private const ATTEMPTS = 5;

    /** @param array<string, mixed> $session */
    public static function begin(array &$session, int $now): void
    {
        $session['second_factor'] = ['expires' => $now + self::LIFETIME, 'failures' => 0];
    }

    /** @param array<string, mixed> $session */
    public static function canAttempt(array $session, int $now): bool
    {
        $challenge = $session['second_factor'] ?? null;

        return is_array($challenge)
            && is_int($challenge['expires'] ?? null)
            && $challenge['expires'] > $now
            && is_int($challenge['failures'] ?? null)
            && $challenge['failures'] < self::ATTEMPTS;
    }

    /** @param array<string, mixed> $session */
    public static function recordFailure(array &$session, int $now): bool
    {
        if (!self::canAttempt($session, $now)) {
            return false;
        }

        $challenge = $session['second_factor'];
        if (!is_array($challenge) || !is_int($challenge['failures'] ?? null)) {
            return false;
        }
        ++$challenge['failures'];
        $session['second_factor'] = $challenge;

        return self::canAttempt($session, $now);
    }
}
