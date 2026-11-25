# PressDoWiki - Fast & Light PHP Wiki Engine
[![Issues](https://img.shields.io/github/issues/PressDo/PressDoWiki?style=for-the-badge)](https://github.com/PressDo/PressDoWiki)
[![Forks](https://img.shields.io/github/forks/PressDo/PressDoWiki.svg?style=for-the-badge)](https://github.com/PressDo/PressDoWiki)
[![Stars](https://img.shields.io/github/stars/PressDo/PressDoWiki.svg?style=for-the-badge)](https://github.com/PressDo/PressDoWiki)
[![License](https://img.shields.io/github/license/PressDo/PressDoWiki.svg?style=for-the-badge)](https://github.com/PressDo/PressDoWiki)
-------------------------
### 이 위키는 현재 제작 중입니다.
### Currently in development.
-------------------------
- 나무위키의 엔진인 the seed를 모방하여 만든 PHP 기반 위키입니다.
- 외부 저장소 없이 사용할 수 있는 자체 클린룸 나무마크 렌더러와 기본 pressdo 스킨을 함께 제공합니다.

## 설치 및 로컬 실행

PHP 8.2 이상과 Composer 2가 필요합니다. MariaDB/MySQL, SQLite, PostgreSQL을
사용할 수 있습니다. PHP에서 선택한 DB의 PDO 드라이버를 활성화하세요.

1. `composer install`을 실행합니다.
2. `templates/config.json`을 `config/config.json`으로 복사합니다.
   DB 연결 정보, `wiki.domain`, `wiki.canonical_url`, `wiki.front_page`,
   `wiki.timezone`과 저장소 설정을 입력합니다.
3. 새 데이터베이스에 [DB별 설치 스키마](#database-setup)를 적용합니다.
   기존 설치는 DB를 백업하고 [업그레이드 절차](docs/update-protocol.md#manual-deployment)를 따릅니다.
4. 저장소 루트에서 `php tools/migrate.php`를 실행합니다.
5. 웹 서버의 문서 루트를 `public/`으로 지정합니다. 로컬에서는
   `php -S 127.0.0.1:8080 -t public`으로 시작할 수 있습니다.

파일 업로드는 로컬 저장소 또는 S3 호환 저장소를 사용할 수 있습니다. 업로드를
활성화하면 PHP의 `upload_max_filesize`와 `post_max_size`를 조정하세요.
방문자 위치 기능에는 MaxMind GeoIP2 City 데이터베이스가 필요합니다.
나무마크를 사용하려면 `wiki.mark`를 `NamuMark`로 설정하세요.
[지원 문법과 제한](docs/namumark-clean-room.md)을 확인할 수 있습니다.

MediaWiki와 BBCode의 렌더링 결과는 안전한 HTML 요소와 링크만 허용합니다.
기존 문서의 임의 HTML 속성, 인라인 스타일, 폼과 iframe은 렌더링에서 걸러집니다.

## Installation and Local Development

### Requirements

- PHP 8.2 or newer
- Composer 2
- PHP extensions required by Composer dependencies and runtime features, including PDO and the driver for your database
- MariaDB/MySQL, SQLite, or PostgreSQL
- A web server that uses `public/` as the document root
- SMTP for account email
- Optional MaxMind GeoIP2 city database, S3-compatible object storage, and S/MIME certificate files

Composer resolves the lock file against PHP 8.2 so releases remain installable on
every supported PHP version. If `composer install` fails locally, check `php -v`
first.

### Configuration

Copy the default configuration into place:

```sh
cp templates/config.json config/config.json
```

Set the database keys, `wiki.domain`, `wiki.canonical_url`, `wiki.front_page`,
`wiki.timezone`, and storage settings in `config/config.json`. Use the public
HTTPS origin as the canonical URL so the PHP session cookie has the Secure flag
when TLS terminates at a reverse proxy. Remember-me and trusted-device cookies
also use the Secure flag. The bundled Korean namespaces load automatically. To
customize namespaces, place `namespace.json` under `config/language/<wiki.language>/`.

### Database Setup

Import the matching schema into an empty database, then run the
[versioned migrations](#database-upgrades). For MariaDB/MySQL, set
`database.type` to `mysql` and enable `pdo_mysql`:

```sh
mysql -u <user> -p <database> < templates/database_scheme.sql
```

For SQLite, set `database.type` to `sqlite`, set `database.name` to the database
file path, and enable `pdo_sqlite`:

```sh
sqlite3 path/to/wiki.sqlite < templates/database_scheme.sqlite.sql
```

For PostgreSQL, set `database.type` to `pgsql` and enable `pdo_pgsql`:

```sh
psql -U <user> -d <database> -f templates/database_scheme.pgsql.sql
```

For either server database, set `database.host`, `database.port`,
`database.name`, `database.user`, and `database.password`.

The application uses PDO with prepared SQL. `SqlDialect` handles differences
such as identifier quoting and binary UUID binding. PostgreSQL search uses
`LIKE`, and the MariaDB installation schema declares a Mroonga search table.

### Install Dependencies

```sh
composer install
```

If the local PHP installation does not have zip support, install the PHP zip extension or an unzip/7z command line tool so Composer can extract packages.

### Database Upgrades

Back up the configured database and run the versioned migrations once from the
CLI before serving the new release. New installations run the same command
after importing their schema.

```sh
php tools/migrate.php
```

Use a database account with permission to create tables for the upgrade. Run a
single migration process for each database. An interrupted run can be retried.
The installation SQL files reset tables and are for new databases. See the
[deployment and verification steps](docs/update-protocol.md#manual-deployment)
for existing installations.

### Run Locally

Point your web server document root at `public/`. An nginx example is available at `templates/server.nginx`.

For a quick PHP built-in server during development:

```sh
php -S 127.0.0.1:8080 -t public
```

### Development Checks

Run the same checks used by CI:

```sh
composer validate --strict
composer format
composer check
```

`composer format` applies the committed PER-CS 2.0 rules to code covered by the
formatting ratchet. `composer check` verifies syntax, formatting, PHPStan level
max, the current test suite, and known dependency advisories.

## 스킨

기본 스킨은 Blade로 렌더링합니다. 사용자 정의 Blade 스킨은
`resources/views/skins/<이름>.blade.php`에 배치하고
`public/skins/<이름>/config.json`으로 등록하세요. 기존 PHP 스킨의
`public/skins/<이름>/layout.php`도 사용할 수 있습니다. Latte 스킨을 사용하던
설치에서는 레이아웃을 Blade로 옮기거나 기본 스킨을 선택하세요.
사용자 정의 스킨의 POST 폼에는 뷰에 전달되는 `$csrfToken`을
`_csrf` 숨은 필드로 포함하세요. Blade에서는 `@include('partials.csrf')`를
사용할 수 있습니다. 스킨의 JavaScript는 외부 스크립트 파일로 등록하세요.

### 지원 스킨
 - pressdo (기본 제공)
 - ~~senkawa~~ (저작권 문제로 배포하지 않습니다.)
 - liberty (예정)
 - vector (예정)
 - buma (예정)

## 이메일 인증

회원가입, 비밀번호 재설정, 이메일을 통한 2단계 인증에는 SMTP 설정이
필요합니다. `config/config.json`에서 `mail.smtp_host`, `mail.smtp_port`,
`mail.smtp_username`, `mail.smtp_password`, `mail.smtp_address`를 설정하세요.
메일 발신자 이름은 `wiki.site_name_en`을 사용합니다.

회원가입 링크는 발급 요청과 같은 IP에서 24시간 동안 사용할 수 있습니다.
비밀번호 재설정 링크는 30분 동안 사용할 수 있습니다. 두 링크는 각각 성공한
요청에서 한 번만 사용되며, 이전 형식의 링크는 새로 발급받아야 합니다.

비밀번호를 재설정하면 로그인 유지 쿠키와 신뢰 기기 쿠키가 즉시 폐기됩니다.
이미 로그인한 브라우저와 진행 중인 2단계 인증은 다음 요청부터 새 비밀번호로
다시 로그인해야 합니다.

## 로그인한 기기

세션 관리 기능을 처음 배포할 때는 마이그레이션 2를 적용합니다. 기존
브라우저는 한 번 다시 로그인하여 세션을 등록합니다. 새 로그인 유지·신뢰
쿠키는 해당 기기의 세션에 연결되며, 서버에는 쿠키의 해시만 저장합니다.
웹 서버가 여러 대라면 모든 인스턴스에 같은 버전을 배포하세요.

**내 정보 → 로그인한 기기**에서 브라우저와 운영체제, 로그인 시각, 최근
활동 시각을 확인할 수 있습니다. 기기별 로그아웃 또는 다른 모든 기기
로그아웃을 실행하면 선택된 세션의 로그인 유지·신뢰 쿠키도 함께 폐기됩니다.
기존 요청이 끝난 뒤 해당 브라우저의 다음 요청부터 적용됩니다.

기기가 요청을 보내면 최근 활동 시각을 갱신하며 갱신 간격은 최소 5분입니다.
로그인 유지·신뢰 쿠키가 없는 세션 기록은 마지막으로 기록된 활동에서
24시간이 지나면 목록에서 만료됩니다.
활성 로그인 유지·신뢰 쿠키는 각 쿠키의 유효 기간까지 관리할 수 있습니다.

## 로그인 시도 제한

로그인은 계정과 접속 IP별로 아래 횟수를 허용하며, 새 브라우저 세션에도
같은 한도가 적용됩니다. 사용자 이름과 이메일은 같은 계정 한도를 공유합니다.

| 항목 | 기간 | 계정당 | IP당 |
| --- | --- | ---: | ---: |
| 비밀번호 확인 | 5분 | 10회 | 60회 |
| 2단계 인증 확인 | 10분 | 20회 | 60회 |
| 로그인 인증 메일 발송 | 15분 | 3회 | 15회 |

한도에 도달하면 `429`와 `Retry-After`를 반환합니다. 한도는 고정된 시간
구간마다 초기화되며, 2단계 인증 요청당 기존 5회 제한도 적용됩니다. 한도는
웹 서버가 제공하는 접속 IP를 사용합니다. 리버스 프록시를 사용하는 경우
신뢰하는 프록시에 한해 웹 서버가 실제 클라이언트 IP를 복원하도록 설정하세요.

## 데이터베이스 통합 테스트

`composer test`는 임시 SQLite DB로 회원가입, 로그인, 비밀번호 재설정,
권한 부여와 감사 기록, 편집 요청의 리비전 생성을 확인합니다. GitHub Actions는
같은 테스트를 MariaDB 10.11과 PostgreSQL 16에서도 실행합니다.
세 DB에서 기존 계정을 보존하는 스키마 업그레이드, 로그인 제한, 다른
계정의 세션 종료 차단, 종료된 기기의 쿠키 재사용 차단을 확인합니다.
권한 부여와 편집 요청 승인도 실제 POST로 검증하며, 요청이 겹쳐도 같은
리비전에 승인이 한 번만 기록되는지 검사합니다. MariaDB CI는 검색 테이블에
InnoDB를 사용하며 Mroonga 검색 동작은 별도 검증 범위입니다.

편집 요청은 작성의 기준이 된 문서 리비전이 최신일 때 승인됩니다.
문서가 그사이 바뀌었다면 현재 내용을 기준으로 새 요청을 작성하세요.
승인과 닫기처럼 양립할 수 없는 동작이 겹치면 한 요청만 성공하고
다른 요청은 `409`를 반환하므로 최신 상태를 확인한 뒤 다시 시도할 수 있습니다.

로컬에서 서버 DB를 검증하려면 테스트 전용 DB에 해당 설치 스키마를 불러온
뒤 `PRESSDO_TEST_DB_DRIVER`를 `mysql` 또는 `pgsql`로 설정하고
`php tests/Integration/DatabaseSecurityFlowTest.php`를 실행합니다. 접속 설정은
`PRESSDO_TEST_DB_HOST`, `PRESSDO_TEST_DB_PORT`, `PRESSDO_TEST_DB_NAME`,
`PRESSDO_TEST_DB_USER`, `PRESSDO_TEST_DB_PASSWORD`로 지정합니다.
테스트는 필요한 마이그레이션을 적용하고 테스트 계정, 세션, 편집 요청과
감사 기록을 생성합니다.

## 편집기 빌드

원문 편집과 미리보기는 PHP 소스 설치에서 바로 사용할 수 있습니다.
태그 빌드의 ZIP에는 Monaco 편집기가 포함됩니다. Git 체크아웃에서 Monaco 탭을
활성화하려면 Node.js 24로 아래 명령을 실행하세요.

```sh
npm --prefix tools/editor ci --ignore-scripts
npm --prefix tools/editor run build
```

생성된 `public/src/editor/`는 CSS, 글꼴, 워커와 라이선스를 포함합니다.
편집기 파일을 CDN 없이 같은 사이트에서 제공하며, `wiki.editor_version`
설정은 더 이상 사용하지 않습니다. 데스크톱에서 Monaco 탭을 처음 열 때
파일을 내려받습니다. 모바일과 빌드가 없는 설치에서는 원문으로 편집합니다.

## 프로젝트 문서

- [구현 현황과 현대화 계획](docs/modernization.md)
- [운영 배포 절차와 자동 업데이트 설계](docs/update-protocol.md)
- [나무마크 지원 문법](docs/namumark-clean-room.md)
- [클린룸 기여 정책](docs/clean-room-policy.md)
