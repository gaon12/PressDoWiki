@php
    use PressDo\App\Infrastructure\Rendering\ViewTime;
@endphp

<div class="wiki-content">
    @if (!empty($error['errbox']))<div class="a e">
        <strong>{{ $lang['msg']['error'] }}</strong>
        <span>{{ $error['message'] }}</span>
    </div>@endif
    @if (!empty($alert['alertbox']))<div class="a">
        <strong>{{ $lang['msg']['alert'] }}</strong>
        <span>{{ $alert['message'] }}</span>
    </div>@endif
    <div>
        <form data-pressdo-thread-show method="POST" action="{{ ($request_uri ?? '') }}" class="d">
            @include('partials.csrf')
            <input type="checkbox" id="no_display_hidden_comment"><label for="no_display_hidden_comment">{{ $lang['msg']['no_display_hidden_comment'] }}</label>
        </form>
        <h2 data-pressdo-thread-topic>{{ $wiki['page']['data']['topic'] }}</h2>
            <div>
                <div class="t">
                    @foreach ($wiki['page']['data']['comments'] as $d)<div class="thread r v">

                            @if (in_array($d['type'], ['status', 'document', 'topic'], true))
                                @php
                                    $sta = true;
                                @endphp
                            @else
                                @php
                                    $sta = false;
                                @endphp
                        @endif
                        <div class="thread c">
                            <div @class(['f' => ($d['contributor_uuid'] == $wiki['page']['data']['initial_author']), 'thread', 'h'])>
                                <span><a id="{{ $d['id'] }}">#{{ $d['id'] }}</a></span>
                                <div v="inline">
                                    <div v="inline-block">
                                        <a @if($d['admin']) data-pressdo-user-member @endif>{{ ($d['author'] ?? '').($d['ip'] ?? '') }}</a>
                                    </div>
                                </div>
                                <span class="d">
                                    {!! ViewTime::absolute((int) ($d['date'] ?? 0)) !!}
                                    @if (!$sta)<div v="inline" class="p">
                                        <div v="inline-block">
                                            <button class="pressdo-btn thread-btn"></button>
                                        </div>
                                    </div>@endif
                                </span>
                                <div class="thread f"></div>
                            </div>
                            <div @class(['s' => $sta, 'thread', 'b'])>
                                <div>
                                    <div class="w">
                                        <div>
                                            @if ($sta)
                                                {!! sprintf($lang['msg']['changed_thread_'.$d['type']], e($d['text'] ?? '')) !!}
                                            @else
                                                {!! $d['text'] !!}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>@endforeach
                </div>
            </div>
        <h3 data-pressdo-discuss-type>{{ $lang['thread']['write_thread_comment'] }}</h3>
            @if ($wiki['page']['data']['updateThreadStatus'] === true)<form method="post">
                @include('partials.csrf')
                [ADMIN] {{ $lang['thread']['updateThreadStatus'] }}
                <select name="status">
                    @foreach (['normal', 'pause', 'close'] as $s)
                        @if ($s != $wiki['page']['data']['status'])<option value="{{ $s }}">{{ $s }}</option>@endif
                    @endforeach
                </select>
                <button class="pressdo-btn" type="submit">{{ $lang['change'] }}</button>
            </form>@endif
            @if ($wiki['page']['data']['updateThreadDocument'] === true)<form method="post">
                @include('partials.csrf')
                [ADMIN] {{ $lang['thread']['updateThreadDocument'] }}
                <input type="text" name="document" value="{{ $wiki['page']['title'] }}">
                <button class="pressdo-btn" type="submit">{{ $lang['change'] }}</button>
            </form>@endif
            @if ($wiki['page']['data']['updateThreadTopic'] === true)<form method="post">
                @include('partials.csrf')
                [ADMIN] {{ $lang['thread']['updateThreadTopic'] }}
                <input type="text" name="topic" value="{{ $wiki['page']['data']['topic'] }}">
                <button class="pressdo-btn" type="submit">{{ $lang['change'] }}</button>
            </form>@endif
        <form method="post" class="editor">
            @include('partials.csrf')
            <ul class="selector">
                <li class="selector"><button class="selector-btn btn-wideright">{{ $lang['editor']['raw'] }}</button></li>
                <li class="selector"><button class="selector-btn btn-wideright">{{ $lang['editor']['preview'] }}</button></li>
            </ul>
            <div data-pressdo-loginform class="c">
                @if ($wiki['page']['data']['status'] != 'normal')
                    @php
                        $disabled1 = 'disabled';
                        $_name = 'text';
                    @endphp
                @else
                    @php
                        $disabled1 = false;
                        $_name = false;
                    @endphp
                @endif
                <textarea data-pressdo-thread id="contentInput" rows="5" @disabled($disabled1) name="{{ $_name }}" required>@if ($wiki['page']['data']['status']!='normal') {{ $lang['msg']['thread_status_'.$wiki['page']['data']['status']] }} @endif</textarea>
                @if (empty($wiki['session']['member']['username']))<p data-pressdo-warning-unlogined>{{ sprintf($lang['msg']['unlogined_comment'], $wiki['session']['ip']) }}</p>@endif
            </div>
            <button type="submit" @disabled($disabled1) class="pressdo-btn btn-wideright btn-blue">{{ $lang['submit'] }}</button>
        </form>
    </div>
</div>
<div data-pressdo-thread-popover="hidden" placement="bottom" class="tooltip popover">
    <div class="wrapper">
        <div class="tooltip-inner popover-inner" style="position:relative;">
            <div>
                <div class="m">
                    <button id="bt-wiki" v="block" class="pressdo-btn" type="button">{{ $lang['thread']['view_original'] }}</button>
                    <button id="bt-origin" v="none" class="pressdo-btn" type="button">{{ $lang['thread']['view_wiki'] }}</button>
                    <button class="pressdo-btn btn-red" type="button">[ADMIN] {{ $lang['thread']['hide_thread_comment'] }}</button>
                </div>
            </div>
        </div>
        <div class="tooltip-arrow popover-arrow" style="left:66px;"></div>
    </div>
</div>
