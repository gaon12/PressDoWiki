<?php

declare(strict_types=1);

use PressDo\App\Services\Mark\NamuMark\Renderer;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failNamuMarkBlockParserTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$source = <<<'NAMUMARK'
> 인용문 '''강조'''
>> 중첩 인용문

 * 첫 항목
  * [[하위 문서|하위 항목]]
 * 마지막 항목

{{{
<script>alert('literal')</script>
'''이 문법은 해석하지 않는다'''
}}}
NAMUMARK;

$result = (new Renderer())->render($source);
$html = $result['html'];

foreach (
    [
        '<blockquote class="wiki-quote">',
        '<p>인용문 <strong>강조</strong></p>',
        '<blockquote><p>중첩 인용문</p></blockquote>',
        '<ul class="wiki-list"><li>첫 항목<ul class="wiki-list"><li><a class="wiki-link"',
        '</li></ul></li><li>마지막 항목</li></ul>',
        '<pre class="wiki-code"><code>&lt;script&gt;alert(&#039;literal&#039;)&lt;/script&gt;',
        '&#039;&#039;&#039;이 문법은 해석하지 않는다&#039;&#039;&#039;</code></pre>',
    ] as $expected
) {
    if (!str_contains($html, $expected)) {
        failNamuMarkBlockParserTest('Missing expected block output: ' . $expected);
    }
}

if (str_contains($html, "<script>alert('literal')</script>") || str_contains($html, '<strong>이 문법은')) {
    failNamuMarkBlockParserTest('Literal blocks must be escaped without inline parsing.');
}

if ($result['links']['link'] !== ['하위 문서']) {
    failNamuMarkBlockParserTest('Links inside supported block structures must be indexed once.');
}

$irregularList = (new Renderer())->render("   * 깊은 시작\n * 얕은 다음\n   * 다시 깊게");
foreach (['깊은 시작', '얕은 다음', '다시 깊게'] as $visibleItem) {
    if (!str_contains($irregularList['html'], $visibleItem)) {
        failNamuMarkBlockParserTest('Irregular indentation must not make list content disappear.');
    }
}

$orderedLists = (new Renderer())->render(
    " 1. 숫자\n 1. 둘째\n a. 영문\n A. 대문자\n i. 로마자\n I. 대문자 로마자",
);
foreach (
    [
        '<ol class="wiki-list wiki-list-decimal"><li>숫자</li><li>둘째</li></ol>',
        '<ol class="wiki-list wiki-list-alpha"><li>영문</li></ol>',
        '<ol class="wiki-list wiki-list-upper-alpha"><li>대문자</li></ol>',
        '<ol class="wiki-list wiki-list-roman"><li>로마자</li></ol>',
        '<ol class="wiki-list wiki-list-upper-roman"><li>대문자 로마자</li></ol>',
    ] as $expectedList
) {
    if (!str_contains($orderedLists['html'], $expectedList)) {
        failNamuMarkBlockParserTest('Missing expected ordered-list output: ' . $expectedList);
    }
}

$unclosed = (new Renderer())->render("{{{\n<script>\n'''still literal'''");
if (
    !str_contains($unclosed['html'], '&lt;script&gt;')
    || str_contains($unclosed['html'], '<script>')
    || str_contains($unclosed['html'], '<strong>')
) {
    failNamuMarkBlockParserTest('An unclosed literal block must remain visible and escaped.');
}

$tooManyLines = implode("\n", array_fill(0, 100_001, 'x'));
try {
    (new Renderer())->render($tooManyLines);
    failNamuMarkBlockParserTest('Documents exceeding the line limit must be rejected.');
} catch (RuntimeException) {
}

echo 'Clean-room NamuMark block parser tests passed.' . PHP_EOL;
