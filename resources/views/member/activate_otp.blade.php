@if ($wiki['page']['data']['error'])<div class="a e">
    <strong>{{ $lang['msg']['error'] }}</strong>
    <span>{{ $lang['msg'][$wiki['page']['data']['error']] }}</span>
</div>@endif
<ol>
    <li>{!! $lang['msg']['activate_otp_1'] !!}</li>
    <li>{!! $lang['msg']['activate_otp_2'] !!}<br><img src="{{ $wiki['page']['data']['qrcode'] }}"></li>
    <li>{!! $lang['msg']['activate_otp_3'] !!}</li>
</ol>
<form class="authform" method="post">
    <div class="form-sheet">
        <label for="pinInput">PIN</label>
        <input class="textfield" type="text" id="pinInput" name="pin" maxlength="6">
    </div>
    <div class="btn-area">
        <button type="submit" class="btn-lightblue pressdo-btn-nonflex">{{ $lang['btn']['activate'] }}</button>
    </div>
</form>
