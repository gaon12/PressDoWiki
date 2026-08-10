<?php

declare(strict_types=1);

use PressDo\App\Services\File\UploadDescription;
use PressDo\App\Services\File\UploadValidationException;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failUploadDescriptionTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$description = UploadDescription::fromInput(
    'CC BY-SA 4.0',
    '스크린샷',
    '설명 본문',
    ['CC BY-SA 4.0'],
    ['스크린샷'],
);
$expected = "[include(틀:이미지 라이선스/CC BY-SA 4.0)]\n[[분류:파일/스크린샷]]\n설명 본문";
if ($description->wikiText('틀', '이미지 라이선스', '분류', '파일') !== $expected) {
    failUploadDescriptionTest('Validated upload metadata should produce deterministic wiki text.');
}

foreach ([
    ['license', 'CC BY-SA 4.0)]' . "\n" . '[[분류:관리자', '스크린샷', 'file_select_license'],
    ['category', 'CC BY-SA 4.0', '스크린샷]]' . "\n" . '[include(틀:공격)', 'file_select_category'],
] as [$field, $license, $category, $expectedError]) {
    try {
        UploadDescription::fromInput($license, $category, '', ['CC BY-SA 4.0'], ['스크린샷']);
        failUploadDescriptionTest("A forged {$field} selection must be rejected.");
    } catch (UploadValidationException $error) {
        if ($error->messageKey !== $expectedError) {
            failUploadDescriptionTest("Unexpected validation error for {$field}: {$error->messageKey}");
        }
    }
}

echo 'Upload description validation tests passed.' . PHP_EOL;
