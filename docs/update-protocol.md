# Core update protocol

## Manual deployment

The [release workflow](../.github/workflows/release.yml) builds a ZIP artifact
from each `v*` tag. It includes production Composer dependencies and the built
Monaco editor. For a deployment from a Git checkout, install Composer
dependencies and build the optional editor as described in the
[README](../README.md#편집기-빌드).

1. Stage the new files and retain the installation's `config/` overrides, any
   custom skins, and its existing upload storage. Keep the document root at
   `public/`.
2. Pause traffic to the installation, then back up the database and the
   installed application files. For a deployment with multiple web instances,
   keep traffic paused until all instances are ready to serve the same version.
3. From the new application directory, confirm that `config/config.json` points
   to the intended database. Run `php tools/migrate.php` once per database using
   credentials allowed to create tables. Record the reported migration numbers.
   Re-running the command after a successful upgrade reports
   `Database is up to date.`
4. Activate the new files and verify that a public page renders and a user can
   sign in and see **내 정보 → 로그인한 기기**. With two browser sessions on a
   test account, confirm that ending the other session sends that browser back
   to login even when it presents its login cookie. Resume traffic after
   verification.

When deploying the session registry for the first time, users sign in again once
to register their browsers. Migration 1 adds `login_rate_limits` and migration 2
adds `member_sessions`. The installer SQL under `templates/` drops application
tables and is reserved for initializing a fresh database.

If verification fails while traffic is paused, restore the matching file and
database backups before reopening the site. Preserve and reconcile any writes
made after cutover before restoring a database snapshot.

## Planned automatic updater

PressDoWiki must never treat a downloadable ZIP as trusted merely because it was
served over HTTPS. The updater is split into explicit stages so a failure cannot
silently leave a partially updated installation.

1. Fetch a small JSON manifest over HTTPS with strict time and size limits.
2. Parse only the versioned manifest schema and verify its RSA/SHA-256 signature
   with the public key bundled in the installed release.
3. Reject releases that are not newer or do not support the running PHP version.
4. Download to a new temporary file outside the web root, then verify both the
   exact byte count and SHA-256 from the signed manifest.
5. Enter maintenance mode, back up application files and the database, extract
   into a staging directory, and run preflight checks there.
6. Replace files with same-filesystem atomic renames, run database migrations,
   clear caches, and leave maintenance mode.
7. If any step fails, restore the file and database backups before serving traffic.

The [manifest parser](../App/Update/ReleaseManifest.php) and
[verifier](../App/Update/ReleaseVerifier.php) cover manifest parsing, signature
and compatibility checks, and verification of a local archive's size and
SHA-256. Automatic fetching, signed artifact publication, staged activation,
and rollback remain planned. Administrators run schema migrations through
the manual deployment procedure.

## Signed payload version 1

The signature covers a UTF-8, LF-separated payload rather than serialized JSON.
It begins with `PressDoWiki release manifest v1`. The following lines contain
the fields below in `key=value` form. The payload ends with an LF.

1. `version`
2. `minimum_php`
3. `maximum_php_exclusive`
4. `archive_url`
5. `sha256`
6. `size`
7. `released_at`

The JSON manifest also carries `signature`, the base64-encoded RSA PKCS#1 v1.5
signature using SHA-256. The archive URL uses HTTPS, `size` is the byte count,
and `sha256` is the lowercase hexadecimal archive digest. The
`released_at` field uses RFC 3339 with whole seconds.
