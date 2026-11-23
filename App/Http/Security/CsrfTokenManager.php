<?php

declare(strict_types=1);

namespace PressDo\App\Http\Security;

use PressDo\App\Core\Request;

/** Issues and verifies session-bound tokens for state-changing HTML forms. */
final class CsrfTokenManager
{
    private const BROWSER_TOKEN = 'browser_csrf';

    /** @param array<mixed> $session */
    public function issueBrowserToken(array &$session): string
    {
        return $this->issue($session, self::BROWSER_TOKEN);
    }

    /** @param array<mixed> $session */
    public function validateBrowserPost(array $session, Request $request): bool
    {
        return $request->isMethod('POST')
            && $this->validate($session, self::BROWSER_TOKEN, $request->postScalarString('_csrf'));
    }

    /** @param array<mixed> $session */
    public function issue(array &$session, string $key): string
    {
        $current = $session[$key] ?? null;
        if (is_string($current) && preg_match('/\A[a-f0-9]{64}\z/D', $current) === 1) {
            return $current;
        }

        $token = bin2hex(random_bytes(32));
        $session[$key] = $token;

        return $token;
    }

    /** @param array<mixed> $session */
    public function validate(array $session, string $key, mixed $submittedToken): bool
    {
        $expectedToken = $session[$key] ?? null;

        return is_string($expectedToken)
            && is_string($submittedToken)
            && $submittedToken !== ''
            && hash_equals($expectedToken, $submittedToken);
    }

    /** @param array<mixed> $session */
    public function consume(array &$session, string $key): void
    {
        unset($session[$key]);
    }
}
