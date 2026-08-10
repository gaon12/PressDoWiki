<?php

declare(strict_types=1);

namespace PressDo\App\Admin\Settings;

use InvalidArgumentException;

/**
 * The allow-list for database-backed settings exposed to administrators.
 *
 * Database connection values deliberately do not belong here: the application
 * needs them before it can read this table. A web installer will own that small
 * bootstrap configuration instead of pretending it is a runtime setting.
 */
final class SettingsCatalog
{
    /** @var list<SettingDefinition> */
    private array $definitions;

    public function __construct()
    {
        $this->definitions = [
            new SettingDefinition('wiki.site_name', '사이트', '사이트 이름', '화면과 메일에 표시할 위키 이름입니다.', SettingType::Text, 'PressDoWiki', 120),
            new SettingDefinition('wiki.site_name_en', '사이트', '영문 사이트 이름', '메일 발신자 등에 사용할 영문 이름입니다.', SettingType::Text, 'PressDoWiki', 120),
            new SettingDefinition('wiki.description', '사이트', '사이트 설명', '검색 결과와 공유 미리보기에 표시할 간단한 설명입니다.', SettingType::Textarea, '', 500),
            new SettingDefinition('wiki.front_page', '사이트', '대문 문서', '루트 주소로 방문했을 때 열 문서 이름입니다.', SettingType::Text, 'Frontpage', 255),
            new SettingDefinition('wiki.domain', '주소', '도메인', 'WebAuthn 검증에 사용할 프로토콜 없는 호스트 이름입니다.', SettingType::Hostname, 'localhost', 253),
            new SettingDefinition('wiki.canonical_url', '주소', '대표 URL', '검색 엔진과 공유 링크에 사용할 사이트의 절대 URL입니다.', SettingType::Url, '', 2048),
            new SettingDefinition('wiki.language', '지역', '언어', '설치된 인터페이스 언어입니다.', SettingType::Choice, 'ko-kr', 16, ['ko-kr']),
            new SettingDefinition('wiki.timezone', '지역', '기본 시간대', 'GeoIP로 시간대를 찾을 수 없을 때 사용할 IANA 시간대입니다.', SettingType::Timezone, 'Asia/Seoul', 64),
            new SettingDefinition('wiki.default_skin', '표시', '기본 스킨', '사용자 선택이 없을 때 사용할 내장 스킨 식별자입니다.', SettingType::Identifier, 'pressdo', 64),
            new SettingDefinition('wiki.mark', '표시', '기본 마크업', '새 문서를 해석할 기본 마크업 문법입니다.', SettingType::Choice, 'MediaWiki', 32, ['MediaWiki', 'NamuMark', 'Markdown', 'BBCode']),
            new SettingDefinition('wiki.geoip2_database', '고급', 'GeoIP 데이터베이스', '선택 사항인 GeoLite2 또는 GeoIP2 City 데이터베이스의 서버 경로입니다.', SettingType::Text, '', 4096),
            new SettingDefinition('member.tos', '회원', '이용 약관', '가입 화면에 표시할 이용 약관입니다.', SettingType::Textarea, '여기에 약관을 입력해 주세요.', 20000),
        ];
    }

    /**
     * @return list<SettingDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * Normalize only catalogued keys. Unknown input is ignored and therefore
     * can never create a new application setting.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $current
     * @return array<string, string>
     */
    public function normalize(array $input, array $current = []): array
    {
        $normalized = [];
        $errors = [];

        foreach ($this->definitions as $definition) {
            $value = $input[$definition->key] ?? $current[$definition->key] ?? $definition->default;

            try {
                $normalized[$definition->key] = $definition->normalize($value);
            } catch (InvalidArgumentException $exception) {
                $errors[$definition->key] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            throw new SettingsValidationException($errors);
        }

        return $normalized;
    }
}
