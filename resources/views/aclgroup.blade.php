@php
    use PressDo\App\Infrastructure\Rendering\ViewTime;
@endphp

@if (empty($wiki['page']['data']['prev_page']))
    @php
        $phref = null;
    @endphp
@else
    @php
        $phref = '/BlockHistory?until='.$wiki['page']['data']['prev_page'];
    @endphp
@endif
@if (empty($wiki['page']['data']['next_page']))
    @php
        $nhref = null;
    @endphp
@else
    @php
        $nhref = '/BlockHistory?from='.$wiki['page']['data']['next_page'];
    @endphp
@endif
@if (!empty($error['errbox']))<div class="a e">
    <strong>{{ $lang['msg']['error'] }}</strong>
    <span>{{ $error['message'] ?? '' }}</span>
</div>@endif
<ul class="aclgroup-tabs">
    @foreach ($wiki['page']['data']['aclgroups'] as $g)<li data-pressdo-aclgroup>
        <a @class(['a' => $wiki['page']['data']['currentgroup'] == $g]) href="/aclgroup?group={{ rawurlencode((string) ($g)) }}">{{ $g }} @if ($wiki['page']['data']['group_removable'])<button d_target="{{ $g }}" class="rmag" msg="{{ sprintf($lang['msg']['aclgroup_delete'], $g) }}" type="button">×</button>@endif</a>
    </li>@endforeach
    @if ($wiki['page']['data']['group_addable'])<li data-pressdo-aclgroup>
        <button id="nag" type="button">+</button>
    </li>@endif
</ul>
<form data-pressdo-aclgroup method="POST" action="{{ ($request_uri ?? '') }}" class="a">
    @include('partials.csrf')
    <input data-pressdo-aclgroup class="pressdo-form" type="hidden" name="group" value="{{ $wiki['page']['data']['currentgroup'] }}">
    <div data-pressdo-aclgroup class="g">
        <select data-pressdo-aclgroup class="pressdo-form" name="mode" id="agmd">
            <option value="ip">{{ $lang['acl']['ip'] }}</option>
            <option value="username">{{ $lang['acl']['username'] }}</option>
        </select>
        <input class="pressdo-form" data-pressdo-aclgroup id="vl" type="text" name="ip" placeholder="CIDR">
        @if (!empty($err[1]['username']))<p class="errmsg">{{ $lang['msg']['err_wrong_username'] }}</p>@endif
        @if (!empty($err[1]['ip']))<p class="errmsg">{{ $lang['msg']['invalid_cidr'] }}</p>@endif
    </div>
    <div data-pressdo-aclgroup class="g">
        <label data-pressdo-aclgroup for="noteInput">{{ $lang['acl']['memo'] }} :</label>
        <input class="pressdo-form" data-pressdo-aclgroup type="text" id="noteInput" name="note">
        @if (!empty($err[1]['note']))<p id="erruser" class="errmsg">{{ sprintf($lang['msg']['err_required_input'], 'note') }}</p>@endif
    </div>
    <div data-pressdo-aclgroup class="g">
        <label data-pressdo-aclgroup>{{ $lang['acl']['expire'] }} :</label>
        <span>
            <input id="dr" name="duration_raw" class="pressdo-form" type="hidden" value="0" i="aclgroup">
            <select class="pressdo-form" id="dur" i="aclgroup">
                <option class="pressdo-form" value="0">{{ $lang['acl']['forever'] }}</option>
                <option class="pressdo-form" value="300">{{ $lang['acl']['300s'] }}</option>
                <option class="pressdo-form" value="600">{{ $lang['acl']['600s'] }}</option>
                <option class="pressdo-form" value="1800">{{ $lang['acl']['1800s'] }}</option>
                <option class="pressdo-form" value="3600">{{ $lang['acl']['3600s'] }}</option>
                <option class="pressdo-form" value="7200">{{ $lang['acl']['7200s'] }}</option>
                <option class="pressdo-form" value="86400">{{ $lang['acl']['86400s'] }}</option>
                <option class="pressdo-form" value="259200">{{ $lang['acl']['259200s'] }}</option>
                <option class="pressdo-form" value="432000">{{ $lang['acl']['432000s'] }}</option>
                <option class="pressdo-form" value="604800">{{ $lang['acl']['604800s'] }}</option>
                <option class="pressdo-form" value="1209600">{{ $lang['acl']['1209600s'] }}</option>
                <option class="pressdo-form" value="1814400">{{ $lang['acl']['1814400s'] }}</option>
                <option class="pressdo-form" value="2419200">{{ $lang['acl']['2419200s'] }}</option>
                <option class="pressdo-form" value="4838400">{{ $lang['acl']['4838400s'] }}</option>
                <option class="pressdo-form" value="7257600">{{ $lang['acl']['7257600s'] }}</option>
                <option class="pressdo-form" value="14515200">{{ $lang['acl']['14515200s'] }}</option>
                <option class="pressdo-form" value="29030400">{{ $lang['acl']['29030400s'] }}</option>
                <option class="pressdo-form" value="raw">{{ $lang['acl']['raw'] }}</option>
            </select>
            <input id="dr_e" class="pressdo-form" type="hidden" name="0" value="0" i="aclgroup">
            <select id="du" class="pressdo-form" v="none" i="aclgroup">
                <option class="pressdo-form" value="1">{{ $lang['acl']['second'] }}</option>
                <option class="pressdo-form" value="60">{{ $lang['acl']['minute'] }}</option>
                <option class="pressdo-form" value="3600">{{ $lang['acl']['hour'] }}</option>
                <option class="pressdo-form" value="86400">{{ $lang['acl']['day'] }}</option>
                <option class="pressdo-form" value="604800">{{ $lang['acl']['week'] }}</option>
            </select>
        </span>
        @if (!empty($err[1]['expire']))<p class="errmsg">{{ sprintf($lang['msg']['err_required_input'], 'expire') }}</p>@endif
        @if (!empty($err[1]['expire_much']))<p class="errmsg">{{ $lang['msg']['err_expire_too_long'] }}</p>@endif
    </div>
    <button class="pressdo-btn-nonflex btn-lightblue" @disabled(!$wiki['page']['data']['list_addable']) type="submit">{{ $lang['acl']['add'] }}</button>
</form>
@include('partials.pagination', ['phref' => $phref, 'nhref' => $nhref])
<form class="s" data-pressdo-aclgroup method="GET">
    <div data-pressdo-aclgroup class="g">
        <input class="pressdo-form" type="text" name="from" placeholder="ID">
        <button class="pressdo-btn-nonflex btn-lightblue" type="submit">Go</button>
    </div>
</form>
<table class="pressdo-table">
    <colgroup>
        <col style="width:150px;">
        <col style="width:150px;">
        <col>
        <col style="width:200px;">
        <col style="width:160px;">
        <col style="width:60px;">
    </colgroup>
    <thead>
        <tr>
            <th>ID</th>
            <th>{{ $lang['acl']['target'] }}</th>
            <th>{{ $lang['acl']['memo'] }}</th>
            <th>{{ $lang['acl']['added'] }}</th>
            <th>{{ $lang['acl']['expired'] }}</th>
            <th>{{ $lang['acl']['action'] }}</th>
        </tr>
    </thead>
    <tbody>
        @if (count($wiki['page']['data']['list'] ?? []) === 0)<td class="s" colspan="5">{{ $lang['msg']['empty_aclgroup'] }}</td>@endif
        @if (count($wiki['page']['data']['list'] ?? []) !== 0)
            @foreach ($wiki['page']['data']['list'] as $li)<tr>
                <td class="s">{{ $li['id'] }}</td>
                <td class="s">{{ $li['target_member'] ?? $li['target_ip'] }}</td>
                <td class="s">{{ $li['comment'] }}</td>
                <td class="s">{!! ViewTime::absolute((int) ($li['datetime'] ?? 0)) !!}</td>
                <td class="s">
                @if ($li['until'] == 0)
                    {{ $lang['acl']['forever'] }}
                @else
                    {!! ViewTime::absolute((int) ($li['until'] ?? 0)) !!}
                @endif
                </td>
                <td><button @disabled(!$wiki['page']['data']['list_removable']) id="acl_delete" acllog_id="{{ $li['id'] }}" class="pressdo-btn-nonflex btn-red" type="button">{{ $lang['acl']['delete'] }}</button></td>
            </tr>@endforeach
        @endif
    </tbody>
</table>
@include('partials.pagination', ['phref' => $phref, 'nhref' => $nhref])
@if (($err[1]['mode'] ?? null) === 'd')
    <div style="z-index: 1000;" id="remove_win" class="inset fixed" style="display:block;">
@else
    <div style="z-index: 1000;" id="remove_win" class="inset fixed" v="none">
@endif
    <div class="inset overlay absolute"></div>
    <div class="inset absolute overlay-container">
        <div class="overlay-content">
            @if (!empty($err[0]))<div class="a e">
                <strong>{{ $lang['msg']['error'] }}</strong>
                <span>{{ $lang['msg'][$err[0]] }}</span>
            </div>@endif
            <form method="POST" action="{{ ($request_uri ?? '') }}">
                @include('partials.csrf')
                <input class="pressdo-form" id="idto_remove" type="hidden" name="id" value>
                <input class="pressdo-form" type="hidden" name="group" value="{{ $wiki['page']['data']['currentgroup'] }}">
                <h4>{{ $lang['acl']['remove_member'] }}</h4>
                <div>
                    <p>ID:</p>
                    <span id="id_to_remove"></span>
                </div>
                <div>
                    <p>{{ $lang['acl']['memo'] }}:</p>
                    <input class="pressdo-form" type="text" name="note">
                    @if (!empty($err[1]['note']))<p id="erruser" class="errmsg">{{ sprintf($lang['msg']['err_required_input'], 'note') }}</p>@endif
                </div>
                <div class="overlay-btns">
                    <button class="pressdo-btn-nonflex btn-lightblue btn-sizeup" type="submit">{{ $lang['acl']['delete'] }}</button>
                    <button class="pressdo-btn-nonflex btn-sizeup" id="cancel_btn" type="button">{{ $lang['cancel'] }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@if (($err[1]['mode'] ?? null) === 'c')
<div style="z-index: 1000;" id="add_win" class="inset fixed" style="display:block;">
@else
<div style="z-index: 1000;" id="add_win" class="inset fixed" v="none">
@endif
    <div class="inset overlay absolute"></div>
    <div class="inset absolute overlay-container">
        <div class="overlay-content">
            @if (!empty($err[0]))<div class="a e">
                <strong>{{ $lang['msg']['error'] }}</strong>
                <span>{{ $lang['msg'][$err[0]] }}</span>
            </div>@endif
            <form method="POST" action="{{ ($request_uri ?? '') }}">
                @include('partials.csrf')
                <h4>{{ $lang['acl']['group_add'] }}</h4>
                <div>
                    <p>{{ $lang['acl']['group_name'] }}:</p>
                    <input class="pressdo-form" type="text" name="name">
                    @if (!empty($err[1]['name']))
                        <p id="erruser" class="errmsg">{{ sprintf($lang['msg']['err_required_input'], 'name') }}</p>
                    @endif
                </div>
                <div class="overlay-btns">
                    <button class="pressdo-btn btn-blue btn-sizeup" type="submit">{{ $lang['btn']['create'] }}</button>
                    <button class="pressdo-btn btn-blue btn-sizeup" id="cancel_btn" type="button">{{ $lang['cancel'] }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
