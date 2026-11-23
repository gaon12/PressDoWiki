@if ($wiki['page']['data']['error'])<div class="a e">
    <strong>{{ $lang['msg']['error'] }}</strong>
    <span>{{ $lang['msg'][$wiki['page']['data']['error']] }}</span>
</div>@endif
<form class="authform" method="post">
    <div class="form-sheet">
        <label for="pinInput">PIN</label>
        <input class="textfield" type="text" id="pinInput" name="pin" maxlength="6">
    </div>
    <div class="btn-area">
        <button type="submit" class="btn-red pressdo-btn-nonflex">{{ $lang['btn']['deactivate'] }}</button>
    </div>
</form>
