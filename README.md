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
2. `templates/`의 `config.json`, `settings.json`, `namespace.json`을 `config/`로 복사합니다.
   `config/config.json`에 DB 연결 정보, `wiki.domain`, `wiki.front_page`, `wiki.timezone`과 저장소 설정을 입력합니다.
3. 아래 DB별 설정에 따라 초기 스키마를 적용합니다.
4. 웹 서버의 문서 루트를 `public/`으로 지정합니다. 로컬에서는
   `php -S 127.0.0.1:8080 -t public`으로 시작할 수 있습니다.

파일 업로드는 로컬 저장소 또는 S3 호환 저장소를 사용할 수 있습니다. 업로드를
활성화하면 PHP의 `upload_max_filesize`와 `post_max_size`를 조정하세요.
방문자 위치 기능에는 MaxMind GeoIP2 City 데이터베이스가 필요합니다.
PostgreSQL 검색은 `LIKE`를 사용합니다.

MediaWiki와 BBCode의 렌더링 결과는 안전한 HTML 요소와 링크만 허용합니다.
기존 문서의 임의 HTML 속성, 인라인 스타일, 폼과 iframe은 렌더링에서 걸러집니다.

## Installation and Local Development

### Requirements

- PHP 8.2 or newer
- Composer 2
- PHP extensions required by Composer dependencies and runtime features, including PDO and the driver for your database
- MariaDB/MySQL, SQLite, or PostgreSQL
- A web server that uses `public/` as the document root
- Optional: MaxMind GeoIP2 city database, S3-compatible object storage, SMTP, and S/MIME certificate files

Composer resolves the lock file against PHP 8.2 so releases remain installable on
every supported PHP version. If `composer install` fails locally, check `php -v`
first.

### Configuration

Create runtime config files from the templates:

```sh
cp templates/config.json config/config.json
cp templates/settings.json config/settings.json
cp templates/namespace.json config/namespace.json
```

Edit `config/config.json` before starting the app. At minimum, set the database keys, `wiki.domain`, `wiki.front_page`, `wiki.timezone`, and storage settings. For local file uploads, use the local storage configuration supported by the application; for S3, fill the `storage.*` keys.

### Database Setup

For MariaDB/MySQL:

```sh
mysql -u <user> -p <database> < templates/database_scheme.sql
```

For SQLite:

```sh
sqlite3 path/to/wiki.sqlite < templates/database_scheme.sqlite.sql
```

Then set `database.type` and `database.name` in `config/config.json`. For SQLite, `database.name` should be the SQLite file path.

For PostgreSQL, set `database.type` to `pgsql` and fill `database.host`, `database.port`, `database.name`, `database.user`, and `database.password`. PostgreSQL support currently shares the generic SQL dialect layer and uses LIKE-based search fallback instead of MySQL full-text search.

```sh
psql -U <user> -d <database> -f templates/database_scheme.pgsql.sql
```

### Install Dependencies

```sh
composer install
```

If the local PHP installation does not have zip support, install the PHP zip extension or an unzip/7z command line tool so Composer can extract packages.

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

회원가입 링크는 발급 요청과 같은 IP에서 24시간 동안 사용할 수 있습니다.
비밀번호 재설정 링크는 30분 동안 사용할 수 있습니다. 두 링크는 각각 성공한
요청에서 한 번만 사용되며, 이전 형식의 링크는 새로 발급받아야 합니다.
