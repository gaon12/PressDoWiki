<?php

declare(strict_types=1);

namespace PressDo\App\Admin\Settings;

use DateTimeZone;
use InvalidArgumentException;

/**
 * Describes one setting that an administrator is allowed to change.
 *
 * Keeping validation beside the definition makes the accepted input explicit
 * and prevents the administrator endpoint from becoming a generic config dump.
 */
final readonly class SettingDefinition
{
    /**
     * @param list<string> $choices
     */
    public function __construct(
        public string $key,
        public string $section,
        public string $label,
        public string $description,
        public SettingType $type,
        public string $default,
        public int $maxLength = 255,
        public array $choices = [],
    ) {}

    public function normalize(mixed $input): string
    {
        if (!is_scalar($input)) {
            throw new InvalidArgumentException('문자열로 표현할 수 있는 값이어야 합니다.');
        }

        $value = $this->type === SettingType::Textarea ? (string) $input : trim((string) $input);
        if (strlen($value) > $this->maxLength) {
            throw new InvalidArgumentException(sprintf('최대 %d바이트까지 입력할 수 있습니다.', $this->maxLength));
        }

        return match ($this->type) {
            SettingType::Boolean => $this->normalizeBoolean($value),
            SettingType::Url => $this->normalizeUrl($value),
            SettingType::Timezone => $this->normalizeTimezone($value),
            SettingType::Hostname => $this->normalizeHostname($value),
            SettingType::Identifier => $this->normalizeIdentifier($value),
            SettingType::Choice => $this->normalizeChoice($value),
            SettingType::Text, SettingType::Textarea => $value,
        };
    }

    private function normalizeBoolean(string $value): string
    {
        return match (strtolower($value)) {
            '1', 'true', 'yes', 'on' => '1',
            '0', 'false', 'no', 'off', '' => '0',
            default => throw new InvalidArgumentException('사용 또는 사용 안 함 중 하나를 선택해야 합니다.'),
        };
    }

    private function normalizeUrl(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $parts = parse_url($value);
        if (
            filter_var($value, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || !in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || !is_string($parts['host'] ?? null)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException('사용자 정보가 없는 HTTP 또는 HTTPS URL이어야 합니다.');
        }

        return rtrim($value, '/');
    }

    private function normalizeTimezone(string $value): string
    {
        if (!in_array($value, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('올바른 IANA 시간대 이름이어야 합니다.');
        }

        return $value;
    }

    private function normalizeHostname(string $value): string
    {
        if ($value === 'localhost') {
            return $value;
        }

        if (filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new InvalidArgumentException('프로토콜과 경로를 제외한 호스트 이름이어야 합니다.');
        }

        return strtolower($value);
    }

    private function normalizeIdentifier(string $value): string
    {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/', $value) !== 1) {
            throw new InvalidArgumentException('영문자, 숫자, 점, 밑줄, 하이픈만 사용할 수 있습니다.');
        }

        return $value;
    }

    private function normalizeChoice(string $value): string
    {
        if (!in_array($value, $this->choices, true)) {
            throw new InvalidArgumentException('제공된 선택지 중 하나를 선택해야 합니다.');
        }

        return $value;
    }
}
