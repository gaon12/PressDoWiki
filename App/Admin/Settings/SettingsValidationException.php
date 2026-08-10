<?php

declare(strict_types=1);

namespace PressDo\App\Admin\Settings;

use RuntimeException;

final class SettingsValidationException extends RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('관리자 설정 입력을 확인해 주세요.');
    }
}
