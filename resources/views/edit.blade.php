@if (!empty($error['errbox']))<div class="a e">
    <strong>{{ $lang['msg']['error'] }}</strong>
    <span>{{ $error['message'] ?? '' }}</span>
</div>@endif
@if (!empty($alert['alertbox']))<div class="a">
    <strong>{{ $lang['msg']['alert'] }}</strong>
    <span>{{ $alert['message'] }}</span>
</div>@endif
@php
    $readonly = in_array($error['code'] ?? '', ['permission_edit', 'permission_edit_request'], true);
    $userAgent = $wiki['session']['ua'] ?? '';
    $mobile = str_contains($userAgent, 'Mobile') || str_contains($userAgent, 'Android');
    $visual = !$readonly && !$mobile && is_file(\PressDo\App\Core\ProjectPaths::public('src/editor/monaco.js'));
    $format = strtolower((string) ($config['wiki.mark'] ?? ''));
    $toolbar = [
        'bold' => ["'''", "'''"],
        'italic' => ["''", "''"],
        'strike' => ['~~', '~~'],
        'link' => ['[[', ']]'],
        'file' => ['[[파일:', ']]'],
        'ref' => ['[* ', ']'],
        'template' => ['[include(틀:', ')]'],
    ];
@endphp
<div class="w"></div>
<form method="post" name="editForm" id="editForm" data-wiki-editor data-document-title="{{ $wiki['page']['title'] }}"
    data-editor-format="{{ $format }}" data-editor-captcha="{{ !empty($config['wiki.use_captcha']) ? '1' : '0' }}" enctype="multipart/form-data">
    @include('partials.csrf')
    @if (!$readonly)<input type="hidden" name="token" value="{{ $wiki['page']['data']['token'] }}">@endif
    <ul class="editor top">
        @if ($visual)
            <li class="editor top">
                <button hidden class="e editor top" data-editor-tab="visual" aria-pressed="false" type="button">{{ $lang['editor']['monaco'] }}</button>
            </li>
        @endif
        <li class="editor top">
            <button class="a e editor top" data-editor-tab="raw" aria-pressed="true" type="button">{{ $lang['editor']['raw'] }}</button>
        </li>
        @if (!$readonly)
            <li class="editor top">
                <button hidden class="e editor top" data-editor-tab="preview" aria-pressed="false" type="button">{{ $lang['editor']['preview'] }}</button>
            </li>
        @endif
        @if ($visual && $format === 'namumark')
            <li hidden data-editor-toolbar class="t editor top">
                @foreach ($toolbar as $action => [$prefix, $suffix])
                    <div class="editor top">
                        <button class="editor top" data-editor-prefix="{{ $prefix }}" data-editor-suffix="{{ $suffix }}" type="button">{{ $lang['editor'][$action] }}</button>
                    </div>
                @endforeach
            </li>
        @endif
    </ul>
    @if (!$readonly)<div role="status" data-editor-status></div>@endif
    <div class="c editor">
        @if ($visual)<div class="editor" data-editor-panel="visual">
            <div class="editor"></div>
        </div>@endif
        <div class="a editor" data-editor-panel="raw">
            <textarea @readonly($readonly) class="editor" id="pressdo-anchor" name="content">{{ $wiki['page']['data']['body']['raw'] }}</textarea>
        </div>
        @if (!$readonly)<div class="p editor" data-editor-panel="preview"><div class="w"></div></div>@endif
    </div>
    @if (!$readonly)
        <div class="g comment">
            <label class="comment" for="logInput">{{ $lang['editor']['summary'] }}</label>
            <input value="{{ $wiki['page']['data']['body']['comment'] ?? '' }}" data-pressdo-edit-summary type="text" name="comment" id="logInput">
        </div>
        <label><input required type="checkbox" name="agree" id="agree"> @if (trim((string) (($config['wiki.editagree_text'] ?? ''))) !== '')<span>{!! ($config['wiki.editagree_text'] ?? '') !!}</span>@endif</label>
        @if (empty($wiki['session']['member']['username']))<p class="unlogined">{{ sprintf($lang['msg']['unlogined_edit'], $wiki['session']['ip']) }}</p>@endif
        <button class="btn-blue pressdo-btn btn-wideright editor" type="submit" id="ef">저장</button>
        @if (!empty($config['wiki.use_captcha']))
            <div class="{{ $api_config['captcha_class_name'] ?? '' }}" data-sitekey="{{ $config['captcha.key'] ?? '' }}" data-callback="onCaptchaSubmit" data-execution="execute" data-size="invisible"></div>
        @endif
    @endif
</form>
