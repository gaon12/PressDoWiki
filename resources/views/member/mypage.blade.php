@php
    use PressDo\App\Infrastructure\Rendering\ViewTime;
@endphp

@if ($wiki['page']['data']['error'])<div class="a e">
    <strong>{{ $lang['msg']['error'] }}</strong>
    <span>{{ $lang['msg'][$wiki['page']['data']['error']] ?? $wiki['page']['data']['error'] }}</span>
</div>@endif
<form id="mypage" method="post">
    <div class="bottom-space">
        <label for="usernameInput">{{ $lang['auth']['username'] }}</label>
        <p>{{ $wiki['session']['member']['username'] }}</p>
        <a class="pressdo-btn-nonflex btn-skyblue" href="/member/change_name">{{ $lang['auth']['change_username'] }}</a>
    </div>
    <div class="bottom-space">
        <label for="emailInput">{{ $lang['auth']['email'] }}</label>
        <p>{{ $wiki['page']['data']['email'] }}</p>
        <a class="pressdo-btn-nonflex btn-skyblue" href="/member/change_email">{{ $lang['auth']['change_email'] }}</a>
    </div>
    <div class="bottom-space">
        <label for="permInput">{{ $lang['auth']['perms'] }}</label>
        <p>{{ implode(', ', $wiki['page']['data']['perms']) }}</p>
    </div>
    <div class="bottom-space">
        <label for="passwordInput">{{ $lang['auth']['password'] }}</label>
        <a class="pressdo-btn-nonflex btn-skyblue" href="/member/change_password">{{ $lang['auth']['change_password'] }}</a>
    </div>
    <div class="bottom-space">
        <label for="skinInput">{{ $lang['auth']['skin'] }}</label>
        <select id="skinInput" name="skin">
            <option value="default">{{ $lang['auth']['skin_default'] }}</option>
            @foreach ($wiki['page']['data']['skins'] as $s)<option>{{ $s }}</option>@endforeach
        </select>
    </div>
    <div class="">
        <label>{{ $lang['auth']['2fa'] }}</label>
        @if (!$wiki['page']['data']['totp'])
            <a class="pressdo-btn-nonflex btn-skyblue" href="/member/activate_otp">{{ $lang['auth']['enable_2fa'] }}</a>
        @else
            <a class="pressdo-btn-nonflex btn-red" href="/member/deactivate_otp">{{ $lang['auth']['disable_2fa'] }}</a>
            <div class="small-subform">
                <input type="hidden" id="webauthnCredential" value="{{ $wiki['page']['data']['webauthn_arg'] ?? '' }}">
                <input name="passkeyName" class="pressdo-form" type="text" placeholder="PassKey Name"><button id="webauthn-add" class="pressdo-btn-nonflex btn-lightblue" type="button">{{ $lang['auth']['webauthn_add'] }}</button>
            </div>
            <table class="pressdo-table">
                <thead>
                    <tr>
                        <th>{{ $lang['auth']['webauthn_name'] }}</th>
                        <th>{{ $lang['auth']['webauthn_registered'] }}</th>
                        <th>{{ $lang['auth']['webauthn_lastused'] }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>@if (count($wiki['page']['data']['webauthn'] ?? []) === 0)<td class="s" colspan="5">{{ $lang['msg']['empty_webauthn'] }}</td>@endif</tr>
                    @foreach ($wiki['page']['data']['webauthn'] as $w)<tr>
                        <td>{{ $w['name'] }}</td>
                        <td>{!! ViewTime::absolute((int) ($w['registered'] ?? 0)) !!}</td>
                        <td>{!! $w['lastuse'] ? ViewTime::absolute((int) ($w['lastuse'] ?? 0)) : 'Not used' !!}</td>
                        <td><button targetname="{{ $w['name'] }}" class="pressdo-btn-nonflex btn-red" type="button">{{ $lang['btn']['delete'] }}</button></td>
                    </tr>@endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="">
        <label>API Token</label>
        <button class="pressdo-btn-nonflex btn-red" type="button">{{ $lang['btn']['issue'] }}</button>
    </div>
    <div class="btn-area">
        <button type="submit" class="btn-lightblue pressdo-btn-nonflex">{{ $lang['change'] }}</button>
    </div>
</form>
