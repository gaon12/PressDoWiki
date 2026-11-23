@if ($wiki['page']['data']['error'])<div class="a e">
    <strong>{{ $lang['msg']['error'] }}</strong>
    <span>{{ $lang['msg'][$wiki['page']['data']['error']] }}</span>
</div>@endif
@if ($wiki['page']['data']['step'] === 0)<form id="loginform" method="post" class="authform">
    @include('partials.csrf')
    <div class="form-sheet">
        <label for="emailInput">{{ $lang['auth']['email'] }}</label>
        <input class="textfield" type="email" id="emailInput" name="email">
        <p id="errmail" class="errmsg" v="none">{{ sprintf($lang['msg']['err_required_input'], $lang['auth']['email']) }}</p>
    </div>
    <div class="form-sheet form-action">
        <div class="btn-area">
            <button type="button" class="btn-blue pressdo-btn" id="chkmail">{{ $lang['btn']['find'] }}</button>
            <div class="{{ $api_config['captcha_class_name'] ?? '' }}" data-sitekey="{{ $config['captcha.key'] ?? '' }}" data-callback="onCaptchaSubmit" data-execution="execute" data-size="invisible"></div>
        </div>
    </div>
</form>@endif
@if ($wiki['page']['data']['step'] === 1)
    {{-- 이메일 인증대기 --}}
    <p>{!! sprintf($lang['msg']['sent_email_recover'], e($post['email'] ?? '')) !!}</p>
    <ul>
        <li>{{ $lang['msg']['check_validity'] }}</li>
        <li>{{ $lang['msg']['check_spam'] }}</li>
        <li>인증 링크는 30분 동안 유효합니다.</li>
    </ul>
@endif
@if ($wiki['page']['data']['step'] === 2)<form id="signupform" class="authform" name="signupform" method="POST">
    @include('partials.csrf')
    <p>{{ $lang['msg']['enter_new_password'] }}</p>
    <div class="form-sheet">
        <label for="password">{{ $lang['auth']['password'] }}</label>
        <input class="textfield" type="password" id="password" name="password">
        <p id="errpwd" class="errmsg" v="none">{{ sprintf($lang['msg']['err_required_input'], $lang['auth']['password']) }}</p>
    </div>
    <div class="form-sheet">
        <label for="password2">{{ $lang['auth']['password2'] }}</label>
        <input class="textfield" type="password" id="password2" name="password2">
        <p id="errpwd21" class="errmsg" v="none">{{ sprintf($lang['msg']['err_required_input'], $lang['auth']['password2']) }}</p><p id="errpwd22" class="errmsg" v="none">{{ $lang['msg']['err_wrong_password2'] }}</p>
    </div>
    <button type="button" class="btn-lightblue pressdo-btn-nonflex btn-right" id="signup">{{ $lang['auth']['change_password'] }}</button>
</form>@endif
