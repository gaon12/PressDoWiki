<?php

declare(strict_types=1);

use PressDo\App\Update\ReleaseManifest;
use PressDo\App\Update\ReleaseVerifier;
use PressDo\App\Update\UpdateException;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failReleaseVerifierTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$keyOptions = [
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];

// Some Windows PHP distributions do not register their OpenSSL configuration
// path. Supplying the adjacent standard file keeps this test portable while
// Linux and correctly configured installations continue to use their default.
$windowsOpenSslConfig = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
if (is_file($windowsOpenSslConfig)) {
    $keyOptions['config'] = $windowsOpenSslConfig;
}

$privateKey = openssl_pkey_new($keyOptions);
if ($privateKey === false) {
    failReleaseVerifierTest('The test environment could not create an RSA key.');
}

$details = openssl_pkey_get_details($privateKey);
if (!is_array($details) || !is_string($details['key'] ?? null)) {
    failReleaseVerifierTest('The test environment could not export the RSA public key.');
}

$archive = tempnam(sys_get_temp_dir(), 'pressdo-release-');
if ($archive === false) {
    failReleaseVerifierTest('The test environment could not create a release fixture.');
}

try {
    $archiveContents = 'verified release archive';
    file_put_contents($archive, $archiveContents);

    $placeholder = base64_encode(str_repeat('x', 256));
    $unsigned = new ReleaseManifest(
        '2.0.0',
        '8.2.0',
        '9.0.0',
        'https://releases.example.test/pressdo-2.0.0.zip',
        hash('sha256', $archiveContents),
        strlen($archiveContents),
        '2026-08-10T12:00:00+09:00',
        $placeholder,
    );

    $signature = '';
    if (!openssl_sign($unsigned->signingPayload(), $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        failReleaseVerifierTest('The test environment could not sign a release manifest.');
    }

    $manifestJson = json_encode([
        'version' => $unsigned->version,
        'minimum_php' => $unsigned->minimumPhp,
        'maximum_php_exclusive' => $unsigned->maximumPhpExclusive,
        'archive_url' => $unsigned->archiveUrl,
        'sha256' => $unsigned->sha256,
        'size' => $unsigned->size,
        'released_at' => $unsigned->releasedAt,
        'signature' => base64_encode($signature),
    ], JSON_THROW_ON_ERROR);
    $manifest = ReleaseManifest::fromJson($manifestJson);
    $verifier = new ReleaseVerifier($details['key']);

    if (!$verifier->hasValidSignature($manifest)) {
        failReleaseVerifierTest('A correctly signed release manifest should be trusted.');
    }

    if (!$verifier->archiveMatches($manifest, $archive)) {
        failReleaseVerifierTest('The expected release archive should match its size and SHA-256.');
    }

    $verifier->assertInstallable($manifest, '1.9.0', '8.2.0');

    $tampered = new ReleaseManifest(
        '2.0.1',
        $manifest->minimumPhp,
        $manifest->maximumPhpExclusive,
        $manifest->archiveUrl,
        $manifest->sha256,
        $manifest->size,
        $manifest->releasedAt,
        $manifest->signature,
    );
    if ($verifier->hasValidSignature($tampered)) {
        failReleaseVerifierTest('Changing signed release metadata must invalidate its signature.');
    }

    try {
        $verifier->assertInstallable($manifest, '1.9.0', '9.0.0');
        failReleaseVerifierTest('An incompatible PHP runtime must reject the release.');
    } catch (UpdateException) {
    }

    file_put_contents($archive, 'tampered release archive');
    if ($verifier->archiveMatches($manifest, $archive)) {
        failReleaseVerifierTest('A modified archive must fail size or hash verification.');
    }
} finally {
    unlink($archive);
}

echo 'Core release verifier tests passed.' . PHP_EOL;
