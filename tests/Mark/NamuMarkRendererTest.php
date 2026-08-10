<?php

declare(strict_types=1);

use PressDo\App\Services\Mark\NamuMark\Renderer;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failNamuMarkRendererTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$source = <<<'NAMUMARK'
= <script>alert(1)</script> =

'''굵게''' ''기울임'' __밑줄__ ~~취소~~ --취소 2--
[[내부 문서|안전한 링크]] [[https://example.test/path?q=1|외부 링크]]
[[javascript:alert(1)|위험한 링크]]
[[분류:보안]] [[분류:보안]]
NAMUMARK;

$result = (new Renderer())->render($source);
$html = $result['html'];

foreach (['<strong>굵게</strong>', '<em>기울임</em>', '<u>밑줄</u>', '<del>취소</del>', '<del>취소 2</del>'] as $expected) {
    if (!str_contains($html, $expected)) {
        failNamuMarkRendererTest('The NamuMark renderer is missing expected inline output: ' . $expected);
    }
}

if (str_contains($html, '<script>') || str_contains($html, 'href="javascript:')) {
    failNamuMarkRendererTest('NamuMark input must not inject scripts or unsafe URL schemes.');
}

if (!str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;')) {
    failNamuMarkRendererTest('Unsupported HTML should remain visible as escaped text.');
}

if (!str_contains($html, 'href="/w/%EB%82%B4%EB%B6%80%20%EB%AC%B8%EC%84%9C"')) {
    failNamuMarkRendererTest('Internal links should use the local encoded wiki route.');
}

if ($result['links']['link'] !== ['내부 문서']) {
    failNamuMarkRendererTest('Internal document targets should be returned for backlink indexing.');
}

if ($result['categories'] !== ['보안' => []] || $result['links']['category'] !== ['보안' => []]) {
    failNamuMarkRendererTest('Category links should return the metadata shape expected by backlink indexing.');
}

if (str_contains($html, '분류:보안')) {
    failNamuMarkRendererTest('Category declarations should be metadata rather than article body links.');
}

try {
    (new Renderer())->render(str_repeat('x', 2_097_153));
    failNamuMarkRendererTest('Oversized markup input should be rejected before parsing.');
} catch (RuntimeException) {
}

echo 'Clean-room NamuMark renderer tests passed.' . PHP_EOL;
