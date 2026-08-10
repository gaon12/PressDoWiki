# Core update protocol

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

The current implementation covers stages 2 through 4 as pure, tested trust
boundaries. It intentionally does not yet overwrite live files. Network fetching,
the administrator status screen, backup adapters, staged extraction, migration,
atomic activation, and rollback will be added as separate reviewed commits.

## Signed payload version 1

The signature covers a UTF-8, LF-separated payload rather than serialized JSON.
It begins with `PressDoWiki release manifest v1`, contains each manifest field in
the documented order, and ends in an LF. JSON whitespace and key order therefore
cannot change what is authenticated.

Required JSON fields are `version`, `minimum_php`, `maximum_php_exclusive`,
`archive_url`, `sha256`, `size`, `released_at`, and `signature`. Archives must use
HTTPS. The signature is base64-encoded RSA PKCS#1 v1.5 with SHA-256; the archive
digest is lowercase hexadecimal SHA-256.
