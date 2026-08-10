@php
    $data = $wiki['page']['data'] ?? [];
    $code = is_string($data['code'] ?? null) ? $data['code'] : 'unknown_error';
    $providedMessage = $data['message'] ?? null;
    $translatedMessage = $lang['msg'][$code] ?? null;
    $message = is_string($providedMessage) && $providedMessage !== ''
        ? $providedMessage
        : (is_string($translatedMessage) ? $translatedMessage : $code);
    $messageWithLines = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $message);
    $plainMessage = strip_tags($messageWithLines);
    $documentTitle = $uri_data['title'] ?? null;
@endphp

<section class="wiki-content" role="alert" aria-labelledby="error-title">
    <h2 id="error-title">{{ $lang['msg']['error'] ?? '오류' }}</h2>
    <p class="wiki-error-message">{{ $plainMessage }}</p>

    @if (str_starts_with($code, 'permission_') && is_string($documentTitle) && $documentTitle !== '')
        <p><a href="/acl/{{ rawurlencode($documentTitle) }}">ACL 탭 확인</a></p>
    @endif
</section>
