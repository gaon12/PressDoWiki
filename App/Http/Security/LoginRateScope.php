<?php

declare(strict_types=1);

namespace PressDo\App\Http\Security;

enum LoginRateScope: string
{
    case PasswordIp = 'password:ip';
    case PasswordAccount = 'password:account';
    case FactorIp = 'factor:ip';
    case FactorAccount = 'factor:account';
    case MailIp = 'mail:ip';
    case MailAccount = 'mail:account';

    /** @return array{0: positive-int, 1: positive-int} Maximum attempts and window length in seconds. */
    public function policy(): array
    {
        return match ($this) {
            self::PasswordIp => [60, 300],
            self::PasswordAccount => [10, 300],
            self::FactorIp => [60, 600],
            self::FactorAccount => [20, 600],
            self::MailIp => [15, 900],
            self::MailAccount => [3, 900],
        };
    }
}
