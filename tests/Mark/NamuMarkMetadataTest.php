<?php

declare(strict_types=1);

use PressDo\App\Services\Mark\NamuMark\Renderer;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failNamuMarkMetadataTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$redirect = (new Renderer())->render("#redirect 새 문서#문단\n이 설명은 안전하게 렌더링된다.");
if ($redirect['links']['redirect'] !== ['새 문서#문단']) {
    failNamuMarkMetadataTest('A valid first-line redirect should be returned for the controller.');
}

if (str_contains($redirect['html'], '#redirect') || !str_contains($redirect['html'], '이 설명은 안전하게')) {
    failNamuMarkMetadataTest('The redirect directive should be hidden without discarding later text.');
}

$koreanRedirect = (new Renderer())->render('#넘겨주기 대상 문서');
if ($koreanRedirect['links']['redirect'] !== ['대상 문서']) {
    failNamuMarkMetadataTest('The public Korean redirect spelling should be supported.');
}

foreach (
    [
        "#redirect https://example.test\n본문",
        "#redirect javascript:alert(1)\n본문",
        "본문\n#redirect 뒤늦은 지시어",
    ] as $unsafeOrMisplaced
) {
    $result = (new Renderer())->render($unsafeOrMisplaced);
    if ($result['links']['redirect'] !== []) {
        failNamuMarkMetadataTest('External, unsafe, or misplaced redirects must not be registered.');
    }
}

$invalidCategory = (new Renderer())->render('[[분류:]] [[분류:' . str_repeat('a', 256) . ']]');
if ($invalidCategory['links']['category'] !== [] || !str_contains($invalidCategory['html'], '분류:')) {
    failNamuMarkMetadataTest('Invalid categories must remain visible instead of becoming metadata.');
}

echo 'Clean-room NamuMark metadata tests passed.' . PHP_EOL;
