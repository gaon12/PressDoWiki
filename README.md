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
- 나무마크는 기본적으로 제공되지 않으며, 직접 설치하셔야 합니다.

 ### 개발 환경
 - PHP 8.3
 - MariaDB

 ### 요구 사항
 - PHP가 설치되어 있어야 합니다.
 - composer로 패키지 설치가 가능해야 합니다.
 - MySQL, MariaDB, SQLite 중 하나가 설치되어 있어야 합니다. SQLite를 사용할 경우 PHP `pdo_sqlite` 확장이 필요합니다.
 - 확인이 필요한 항목: ~~PostgreSQL, CUBRID, Oracle Database, MSSQL, Firebird, IBM DB2~~
 - SQLite 초기 스키마는 `templates/database_scheme.sqlite.sql`을 사용하세요. `database.type`은 `sqlite`, `database.name`은 SQLite 파일 경로 또는 `:memory:`로 설정합니다.
 - php-geoip (PECL 확장) 또는 Maxmind GeoIP2 데이터베이스 (city)가 있어야 합니다: php-geoip가 우선 적용됩니다.
 - 파일이 업로드될 공간이 있어야 합니다. (S3, Local 중 선택)
 - ngram Parser가 지원되는 데이터베이스를 사용하거나 별도의 검색 엔진 소프트웨어가 설치되어 있어야 합니다.

 ### 사전 설정
 - 파일 업로드 사용 여부를 설정하세요.
 - 지원되는 Database가 설치되어 있어야 합니다.
 - 위키에 업로드될 파일 크기에 맞게 php.ini에서 upload_max_filesize와 post_max_size를 조정해 주세요.
 - [templates/server.nginx](https://github.com/PressDo/PressDoWiki/blob/dev/templates/server.nginx)를 참고해 웹 서버를 세팅해 주세요.

 ### 설치 과정
 테스트 위키에 추후 업로드 예정입니다.

## Installation and Local Development

### Requirements

- PHP 8.3 or newer
- Composer 2
- PHP extensions required by Composer dependencies and runtime features, including PDO and the driver for your database
- MariaDB/MySQL, SQLite, or PostgreSQL
- A web server that uses `public/` as the document root
- Optional: MaxMind GeoIP2 city database, S3-compatible object storage, SMTP, and S/MIME certificate files

The current lock file is generated for PHP 8.3 or newer. If `composer install` fails locally, check `php -v` first.

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
composer check
```

`composer check` runs syntax linting and the current lightweight PHP test suite.

 ### 지원 스킨
 - ~~senkawa~~ (저작권 문제로 배포하지 않습니다.)
 - liberty (예정)
 - vector (예정)
 - buma (예정)
