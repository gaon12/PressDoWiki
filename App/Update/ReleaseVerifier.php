<?php

declare(strict_types=1);

namespace PressDo\App\Update;

use OpenSSLAsymmetricKey;

/**
 * Verifies release authenticity before any updater code may touch live files.
 */
final class ReleaseVerifier
{
    private readonly OpenSSLAsymmetricKey $publicKey;

    public function __construct(string $publicKeyPem)
    {
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            throw new UpdateException('The bundled release public key is invalid.');
        }

        $this->publicKey = $publicKey;
    }

    public function hasValidSignature(ReleaseManifest $manifest): bool
    {
        $signature = base64_decode($manifest->signature, true);
        if ($signature === false) {
            return false;
        }

        $result = openssl_verify(
            $manifest->signingPayload(),
            $signature,
            $this->publicKey,
            OPENSSL_ALGO_SHA256,
        );

        if ($result === -1) {
            throw new UpdateException('OpenSSL could not verify the release signature.');
        }

        return $result === 1;
    }

    public function archiveMatches(ReleaseManifest $manifest, string $archivePath): bool
    {
        if (!is_file($archivePath)) {
            return false;
        }

        $size = filesize($archivePath);
        $hash = hash_file('sha256', $archivePath);

        return $size === $manifest->size
            && is_string($hash)
            && hash_equals($manifest->sha256, $hash);
    }

    public function assertInstallable(
        ReleaseManifest $manifest,
        string $installedVersion,
        string $phpVersion = PHP_VERSION,
    ): void {
        if (!$this->hasValidSignature($manifest)) {
            throw new UpdateException('Release manifest signature is not trusted.');
        }

        if (!$manifest->supportsPhp($phpVersion)) {
            throw new UpdateException('This release does not support the current PHP version.');
        }

        if (!$manifest->isNewerThan($installedVersion)) {
            throw new UpdateException('The release is not newer than the installed version.');
        }
    }
}
