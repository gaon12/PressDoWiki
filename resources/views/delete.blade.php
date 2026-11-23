@if (!empty($error['errbox']))<div class="a e">
    <strong>{{ $lang['msg']['error'] }}</strong>
    <span>{{ $error['message'] }}</span>
</div>@endif
@if (!empty($alert['alertbox']))<div class="a">
    <strong>{{ $lang['msg']['alert'] }}</strong>
    <span>{{ $alert['message'] }}</span>
</div>@endif
<form method="post" name="editForm" id="editForm" enctype="multipart/form-data">
    <div data-pressdo-loginform class="g comment">
        <label class="comment" for="logInput">{{ $lang['editor']['summary'] }}</label>
        <input data-pressdo-edit-summary type="text" name="log" id="logInput">
    </div>
    <label data-pressdo-pagelist><input type="checkbox" name="agree" id="agree"> {{ $lang['msg']['ThisIsNotMove'] }}</label>
    <p class="edit-form"><b>{!! sprintf($lang['msg']['PleaseDontMove'], rawurlencode($wiki['page']['title'])) !!}</b></p>
    @if (empty($wiki['session']['member']['username']))<p class="unlogined">{!! sprintf($lang['msg']['unlogined_edit'], $wiki['session']['ip']) !!}</p>@endif
    <div class="btn-area">
        <button class="pressdo-btn btn-red" editor-msg="{{ $lang['msg']['CheckDelMsg'] }}" type="button" id="delb">{{ $lang['page']['delete'] }}</button>
        <div class="{{ $api_config['captcha_class_name'] ?? '' }}" data-sitekey="{{ $config['captcha.key'] ?? '' }}" data-callback="onCaptchaSubmit" data-execution="execute" data-size="invisible"></div>
    </div>
</form>
