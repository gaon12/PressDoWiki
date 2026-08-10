<?php

declare(strict_types=1);

use PressDo\App\Services\File\FileObjectLocator;
use PressDo\App\Services\File\UploadedFile;
use PressDo\App\Services\File\UploadedImageInspector;
use PressDo\App\Services\File\UploadValidationException;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failUploadedImageInspectorTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function expectUploadValidation(string $messageKey, Closure $operation): void
{
    try {
        $operation();
        failUploadedImageInspectorTest("Expected upload validation failure: {$messageKey}");
    } catch (UploadValidationException $error) {
        if ($error->messageKey !== $messageKey) {
            failUploadedImageInspectorTest("Expected {$messageKey}, got {$error->messageKey}.");
        }
    }
}

$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pressdo-image-' . bin2hex(random_bytes(8));
if (!mkdir($temporaryDirectory)) {
    failUploadedImageInspectorTest('The image test directory could not be created.');
}

$pngPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'image.tmp';
$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
if ($pngBytes === false || file_put_contents($pngPath, $pngBytes) === false) {
    failUploadedImageInspectorTest('The PNG fixture could not be created.');
}

$file = UploadedFile::fromPhpFiles([
    'name' => 'C:\\fakepath\\pixel.png',
    'tmp_name' => $pngPath,
    'error' => UPLOAD_ERR_OK,
], static fn(string $path): bool => $path === $pngPath);
$image = (new UploadedImageInspector())->inspect($file, '파일:pixel.png');
if (
    $file->originalName !== 'pixel.png'
    || $image->extension !== 'png'
    || $image->width !== 1
    || $image->height !== 1
    || $image->sha256 !== hash('sha256', $pngBytes)
    || $image->objectKey() !== substr($image->sha256, 0, 2) . '/' . $image->sha256 . '.png'
) {
    failUploadedImageInspectorTest('A valid PNG should produce trusted metadata and a content-addressed key.');
}

$key = FileObjectLocator::keyForDocument($image->sha256, '파일:pixel.png');
if (
    FileObjectLocator::publicPath('Local', $key) !== '/files/' . $key->value
    || FileObjectLocator::publicPath('S3', $key) !== '/' . $key->value
) {
    failUploadedImageInspectorTest('Public object paths must match the configured storage layout.');
}

expectUploadValidation('err_invalid_fileext', static function () use ($file): void {
    (new UploadedImageInspector())->inspect($file, '파일:pixel.jpg');
});

$svgPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'active.svg';
file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
$svg = new UploadedFile('active.svg', $svgPath);
expectUploadValidation('err_invalid_file', static function () use ($svg): void {
    (new UploadedImageInspector())->inspect($svg, '파일:active.svg');
});

expectUploadValidation('err_file_toobig', static function (): void {
    UploadedFile::fromPhpFiles(['error' => UPLOAD_ERR_INI_SIZE]);
});
expectUploadValidation('err_file_upload_partial', static function (): void {
    UploadedFile::fromPhpFiles(['error' => UPLOAD_ERR_PARTIAL]);
});
expectUploadValidation('err_file_upload_failed', static function (): void {
    UploadedFile::fromPhpFiles(['error' => UPLOAD_ERR_NO_FILE]);
});
expectUploadValidation('err_invalid_file', static function () use ($pngPath): void {
    UploadedFile::fromPhpFiles([
        'name' => 'pixel.png',
        'tmp_name' => $pngPath,
        'error' => UPLOAD_ERR_OK,
    ], static fn(string $path): bool => false);
});

unlink($pngPath);
unlink($svgPath);
rmdir($temporaryDirectory);

echo 'Uploaded image inspection tests passed.' . PHP_EOL;
