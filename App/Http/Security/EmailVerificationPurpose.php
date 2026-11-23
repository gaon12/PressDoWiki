<?php

declare(strict_types=1);

namespace PressDo\App\Http\Security;

enum EmailVerificationPurpose: string
{
    case Signup = 'signup';
    case PasswordReset = 'password-reset';

    public function lifetime(): int
    {
        return match ($this) {
            self::Signup => 86_400,
            self::PasswordReset => 1_800,
        };
    }
}
