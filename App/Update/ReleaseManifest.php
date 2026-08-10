<?php

declare(strict_types=1);

namespace PressDo\App\Update;

use JsonException;

/**
 * Immutable metadata for one official release archive.
 *
 * The signing payload is deliberately line-based and versioned. This avoids
 * depending on JSON key order or whitespace when a manifest is verified.
 */
final readonly class ReleaseManifest
{
    public function __construct(
        public string $version,
        public string $minimumPhp,
        public string $maximumPhpExclusive,
        public string $archiveUrl,
        public string $sha256,
        public int $size,
        public string $releasedAt,
        public string $signature,
    ) {
        self::validateVersion($version, 'version');
        self::validateVersion($minimumPhp, 'minimum_php');
        self::validateVersion($maximumPhpExclusive, 'maximum_php_exclusive');

        if (version_compare($minimumPhp, $maximumPhpExclusive, '>=')) {
            throw new UpdateException('The PHP compatibility range is empty.');
        }

        $url = parse_url($archiveUrl);
        if (
            !is_array($url)
            || ($url['scheme'] ?? null) !== 'https'
            || !is_string($url['host'] ?? null)
            || isset($url['user'])
            || isset($url['pass'])
            || isset($url['fragment'])
        ) {
            throw new UpdateException('Release archives must use an HTTPS URL without credentials or a fragment.');
        }

        if (preg_match('/\A[a-f0-9]{64}\z/', $sha256) !== 1) {
            throw new UpdateException('Release archive SHA-256 must be 64 lowercase hexadecimal characters.');
        }

        if ($size < 1) {
            throw new UpdateException('Release archive size must be positive.');
        }

        if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})\z/', $releasedAt) !== 1) {
            throw new UpdateException('Release time must be an RFC 3339 timestamp without fractional seconds.');
        }

        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false || strlen($decodedSignature) < 64) {
            throw new UpdateException('Release signature must be valid base64 with at least 64 decoded bytes.');
        }
    }

    /**
     * @throws UpdateException when untrusted input does not match the schema
     */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UpdateException('Release manifest is not valid JSON.', 0, $exception);
        }

        if (!is_array($data)) {
            throw new UpdateException('Release manifest must be a JSON object.');
        }

        $size = $data['size'] ?? null;
        if (!is_int($size)) {
            throw new UpdateException('Release manifest field "size" must be an integer.');
        }

        return new self(
            self::stringField($data, 'version'),
            self::stringField($data, 'minimum_php'),
            self::stringField($data, 'maximum_php_exclusive'),
            self::stringField($data, 'archive_url'),
            self::stringField($data, 'sha256'),
            $size,
            self::stringField($data, 'released_at'),
            self::stringField($data, 'signature'),
        );
    }

    public function signingPayload(): string
    {
        return implode("\n", [
            'PressDoWiki release manifest v1',
            'version=' . $this->version,
            'minimum_php=' . $this->minimumPhp,
            'maximum_php_exclusive=' . $this->maximumPhpExclusive,
            'archive_url=' . $this->archiveUrl,
            'sha256=' . $this->sha256,
            'size=' . $this->size,
            'released_at=' . $this->releasedAt,
            '',
        ]);
    }

    public function supportsPhp(string $phpVersion): bool
    {
        return version_compare($phpVersion, $this->minimumPhp, '>=')
            && version_compare($phpVersion, $this->maximumPhpExclusive, '<');
    }

    public function isNewerThan(string $installedVersion): bool
    {
        return version_compare($this->version, $installedVersion, '>');
    }

    private static function validateVersion(string $version, string $field): void
    {
        if (preg_match('/\A(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?\z/', $version) !== 1) {
            throw new UpdateException(sprintf('Release manifest field "%s" must be a semantic version.', $field));
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function stringField(array $data, string $field): string
    {
        $value = $data[$field] ?? null;
        if (!is_string($value) || $value === '' || str_contains($value, "\n") || str_contains($value, "\r")) {
            throw new UpdateException(sprintf('Release manifest field "%s" must be a non-empty single-line string.', $field));
        }

        return $value;
    }
}
