@php
    use PressDo\App\Infrastructure\Rendering\ViewTime;
@endphp

@php
    $d = $wiki['page']['data']['body'];
@endphp
<h3 class="subject">
    @if ($d['contributor_m'] === null)
        @php
            $idset[$d['contributor_uuid']] = ['IP', $d['contributor_i']];
        @endphp
        @php
            $user =
            '<div class="context-menu v-popper v-popper--theme-contextmenu"><a style="'
            .e($d['contributor_style']).'" href="/contribution/'.e($d['contributor_uuid']).'/document" class="user" aria-describedby="contextmenu-'
            .e($d['contributor_uuid']).'">'.e($d['contributor_i']).'</a></div>';
        @endphp
    @else
        @php
            $idset[$d['contributor_uuid']] = [$lang['acl']['member'].($d['contributor_admin'] ? ' ('.$lang['perm']['admin'].')':''), $d['contributor_m']];
        @endphp
        @php
            $user =
            '<div class="context-menu v-popper v-popper--theme-contextmenu"><a style="'
            .e($d['contributor_style']).'" href="/w/'.rawurlencode('사용자:'.e($d['contributor_m'])).'" class="user user-member" aria-describedby="contextmenu-'
            .e($d['contributor_uuid']).'">'.e($d['contributor_m']).'</a></div>';
        @endphp
    @endif
    {!! sprintf($lang['edit_request']['requested'], $user, ViewTime::absolute((int) ($d['createdtime'] ?? 0))) !!}
</h3>
<hr class="page-hr">
<div class="bottom-space">
    @if ($d['lengthdiff'] > 0)
        @php
            $color = 'green';
            $flag = '+';
        @endphp
    @elseif ($d['lengthdiff'] == 0)
        @php
            $color = 'gray';
            $flag = '';
        @endphp
    @elseif ($d['lengthdiff'] < 0)
        @php
            $color = 'red';
            $flag = '';
        @endphp
    @endif
    <label class="inline-data">{{ $lang['edit_request']['baserev'] }}</label> r{{ $d['baserev'] }} <span class="history-{{ $color }}">{{ $flag.$d['lengthdiff'] }}</span>
</div>
<div class="bottom-space">
    <label class="inline-data">{{ $lang['edit_request']['comment'] }}</label> {{ $d['comment'] }}
</div>
<div style="z-index: 1000;" id="remove_win" class="inset fixed" v="none">
    <div class="inset overlay absolute"></div>
    <div class="inset absolute overlay-container">
        <div class="overlay-content">
            <form method="POST" action="{{ $uri_data['path'] }}/close">
                <input class="pressdo-form" id="idto_remove" type="hidden" name="id" value>
                <input class="pressdo-form" type="hidden" name="group" value="{{ $api['currentgroup'] ?? '' }}">
                <h4>{{ $lang['edit_request']['close'] }}</h4>
                <div>
                    <p>{{ $lang['edit_request']['reason'] }}:</p>
                    <input class="pressdo-form" type="text" name="reason">
                    @if (!empty($err[1]['note']))<p id="erruser" class="errmsg">{{ sprintf($lang['msg']['err_required_input'], 'note') }}</p>@endif
                </div>
                <div>
                    <p>{{ $lang['edit_request']['lock'] }}</p>
                    <input class="pressdo-form" type="checkbox" name="lock" value="Y">
                </div>
                <div class="overlay-btns">
                    <button class="pressdo-btn-nonflex btn-lightblue btn-sizeup" type="submit">{{ $lang['btn']['close'] }}</button>
                    <button class="pressdo-btn-nonflex btn-sizeup" id="cancel_btn" type="button">{{ $lang['cancel'] }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="w">
    {!! $wiki['page']['data']['editor_comment'] !!}
</div>
<div class="info-box">
    <div>
    @if ($d['status'] !== 'open')
        @if ($d['executor_m'] === null)
            @php
                $idset[$d['executor_uuid']] = ['IP', $d['executor_i']];
            @endphp
            @php
                $exec =
                '<div class="context-menu v-popper v-popper--theme-contextmenu"><a style="'
                .e($d['executor_style']).'" href="/contribution/'.e($d['executor_uuid']).'/document" class="user" aria-describedby="contextmenu-'
                .e($d['executor_uuid']).'">'.e($d['executor_i']).'</a></div>';
            @endphp
        @else
            @php
                $idset[$d['executor_uuid']] = [$lang['acl']['member'].($d['executor_admin'] ? ' ('.$lang['perm']['admin'].')':''), $d['executor_m']];
            @endphp
            @php
                $exec =
                '<div class="context-menu v-popper v-popper--theme-contextmenu"><a style="'
                .e($d['executor_style']).'" href="/w/'.rawurlencode('사용자:'.e($d['executor_m'])).'" class="user user-member" aria-describedby="contextmenu-'
                .e($d['executor_uuid']).'">'.e($d['executor_m']).'</a></div>';
            @endphp
        @endif
    @endif
    @php
        $exec ??= '';
    @endphp
        @if ($d['status'] == 'accepted')
            @php
                $acc = ' <span class="history-btns">(<a href="/w/'.rawurlencode((string) ($uri_data['title'] ?? '')).'?uuid='.rawurlencode((string) $d['uuid']).'">'.$lang['history']['view'].'</a> | <a href="/raw/'
                .rawurlencode((string) ($uri_data['title'] ?? '')).'?uuid='.rawurlencode((string) $d['uuid']).'">RAW</a> | <a href="/diff/'
                .rawurlencode((string) ($uri_data['title'] ?? '')).'?uuid='.rawurlencode((string) $d['uuid']).'">'.$lang['history']['diff'].'</a>)</span> ';
            @endphp
        @else
            @php
                $acc = '';
            @endphp
        @endif
        <h4>{{ $lang['edit_request']['msg_state_'.$d['status']] }}</h4>
        <div>{!! sprintf($lang['edit_request']['msg_'.$d['status']], ViewTime::absolute((int) ($d['lastedit'] ?? 0)), $exec, e($d['acceptrev'] ?? '').$acc) !!}</div>
        @if (($d['status'] == 'close' || $d['status'] == 'locked') && !empty($d['reason']))<p>{{ $lang['edit_request']['reason'] }} : {{ $d['reason'] }}</p>@endif
        <div class="inline-buttonset">
            @if ($d['status'] == 'close' || $d['status'] == 'locked')<form method="post" action="{{ $uri_data['path'] }}/reopen">
                <button @disabled(!$wiki['page']['data']['can_reopen']) class="pressdo-btn-nonflex btn-lightgreen btn-sizeup" type="submit">Reopen</button>
            </form>@endif
            @if ($d['status'] == 'open')
                <form method="post" action="{{ $uri_data['path'] }}/accept">
                    <button @disabled(!$wiki['page']['data']['can_accept']) class="pressdo-btn-nonflex btn-lightgreen btn-sizeup" type="submit">Accept</button>
                </form>
                <button id="acl_delete" @disabled(!$wiki['page']['data']['can_close']) class="pressdo-btn-nonflex btn-sizeup">Close</button>
                <a @disabled(!$wiki['page']['data']['can_edit']) href="{{ $uri_data['path'] }}/edit" class="pressdo-btn-nonflex btn-skyblue btn-sizeup">Edit</a>
            @endif
        </div>
    </div>
</div>
@if ($d['status'] !== 'accepted')<form>
    <ul class="editor top">
        <li class="editor top">
            <button class="a e editor top" id="d" type="button">{{ $lang['edit_request']['diff'] }}</button>
        </li>
        <li class="editor top">
            <button id="p" class="e editor top" type="button">{{ $lang['editor']['preview'] }}</button>
        </li>
    </ul>
    <div class="c editor">
        <div class="a editor" id="d">
            {!! $wiki['page']['data']['diff'] !!}
        </div>
        <div id="p" class="p editor"><div class="w">
            {!! $wiki['page']['data']['preview'] !!}
        </div></div>
    </div>
</form>@endif
@include('partials.userpopper', ['idset' => $idset])
