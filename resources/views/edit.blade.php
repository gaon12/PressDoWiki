@if (!empty($error['errbox']))<div class="a e">
    <strong>{{ $lang['msg']['error'] }}</strong>
    <span>{{ $error['message'] ?? '' }}</span>
</div>@endif
@if (!empty($alert['alertbox']))<div class="a">
    <strong>{{ $lang['msg']['alert'] }}</strong>
    <span>{{ $alert['message'] }}</span>
</div>@endif
@if (strpos(($wiki['session']['ua'] ?? ''), 'Mobile') !== false || strpos(($wiki['session']['ua'] ?? ''), 'Android') !== false)
    @php
        $mobile = true;
        $toolbarinit = 'none';
    @endphp
@endif
@if (($error['code'] ?? '') == 'permission_edit' || ($error['code'] ?? '') == 'permission_edit_request')
    @php
        $readonly = true;
    @endphp
@endif
@php
    $readonly ??= false;
    $mobile ??= false;
    $toolbarinit ??= false;
@endphp
<div class="w"></div>
<form method="post" name="editForm" id="editForm" enctype="multipart/form-data">
    @if (!$readonly)<input type="hidden" name="token" value="{{ $wiki['page']['data']['token'] }}">@endif
    <ul class="editor top">
        <li class="editor top">
            @if (!$readonly)<button @class(['a' => !$mobile && !$readonly, 'e', 'tr', 'editor', 'top']) id="m" type="button">{{ $lang['editor']['monaco'] }}</button>@endif
        </li>
        <li class="editor top">
            <button @class(['a' => $mobile || $readonly, 'e', 'editor', 'top']) id="r" type="button">{{ $lang['editor']['raw'] }}</button>
        </li>
        <li class="editor top">
            @if (!$readonly)<button id="p" class="e editor top" type="button">{{ $lang['editor']['preview'] }}</button>@endif
        </li>
        @if (!$readonly)<li v="{{ $toolbarinit }}" class="t editor top">
            <div class="editor top">
                <button id="btn-bold" class="editor top" type="button">{{ $lang['editor']['bold'] }}</button>
            </div><div class="editor top">
                <button id="btn-italic" class="editor top" type="button">{{ $lang['editor']['italic'] }}</button>
            </div><div class="editor top">
                <button id="btn-strike" class="editor top" type="button">{{ $lang['editor']['strike'] }}</button>
            </div><div class="editor top">
                <button id="btn-link" class="editor top" type="button">{{ $lang['editor']['link'] }}</button>
            </div><div class="editor top">
                <button id="btn-file" class="editor top" type="button">{{ $lang['editor']['file'] }}</button>
            </div><div class="editor top">
                <button id="btn-ref" class="editor top" type="button">{{ $lang['editor']['ref'] }}</button>
            </div><div class="editor top">
                <button id="btn-template" class="editor top" type="button">{{ $lang['editor']['template'] }}</button>
            </div>
        </li>@endif
    </ul>
    <div class="c editor">
        @if (!$readonly)<div @class(['a' => !$mobile && !$readonly, 'editor']) id="m">
            <div id="monaco" class="editor showUnused"></div>
        </div>@endif<div @class(['a' => $mobile || $readonly, 'editor']) id="r">
            <textarea @readonly($readonly) class="editor" id="pressdo-anchor" name="content">{{ $wiki['page']['data']['body']['raw'] }}</textarea>
        </div>@if (!$readonly)<div id="p" class="p editor"><div class="w"></div></div>@endif
    </div>
    @if (!$readonly)
        <div class="g comment">
            <label class="comment" for="logInput">{{ $lang['editor']['summary'] }}</label>
            <input value="{{ $wiki['page']['data']['body']['comment'] ?? '' }}" data-pressdo-edit-summary type="text" name="comment" id="logInput">
        </div>
        <label><input type="checkbox" name="agree" id="agree"> @if (trim((string) (($config['wiki.editagree_text'] ?? ''))) !== '')<span>{!! ($config['wiki.editagree_text'] ?? '') !!}</span>@endif</label>
        @if (empty($wiki['session']['member']['username']))<p class="unlogined">{{ sprintf($lang['msg']['unlogined_edit'], $wiki['session']['ip']) }}</p>@endif
        <button class="btn-blue pressdo-btn btn-wideright editor" type="button" id="ef" editor-msg="{{ $lang['msg']['please_agree'] }}">저장</button>
        <div class="{{ $api_config['captcha_class_name'] ?? '' }}" data-sitekey="{{ $config['captcha.key'] ?? '' }}" data-callback="onCaptchaSubmit" data-execution="execute" data-size="invisible"></div>
    @endif
</form>
