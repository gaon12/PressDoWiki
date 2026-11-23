@php
    use PressDo\App\Infrastructure\Rendering\ViewTime;
@endphp

@if (!empty($error['errbox']))<div class="a e">
    <strong>{{ $lang['msg']['error'] }}</strong>
    <span>{{ ($error['message'] ?? '').($error['code'] ?? '') }}</span>
</div>@endif
<div>
    @if (($wiki['page']['data']['state'] ?? '') == 'closed_edit_requests' || empty($wiki['page']['data']['state']))
    @if (empty($wiki['page']['data']['state']))<h3>{{ $lang['page']['edit_request'] }}</h3>@endif
    <ul class="lists">
        @foreach ($wiki['page']['data']['edit_requests'] as $e)<li><a href="/edit_request/{{ $e['urlstr'] }}">{{ $lang['page']['edit_request'] }} {{ $e['urlstr'] }}</a></li>@endforeach
    </ul>
    @if (empty($wiki['page']['data']['state']))<p><a href="/discuss/{{ rawurlencode((string) ($uri_data['title'])) }}?state=closed_edit_requests">[{{ $lang['show_closed_edit_requests'] }}]</a></p>@endif
    @endif
    @if (($wiki['page']['data']['state'] ?? '') == 'close'  || empty($wiki['page']['data']['state']))
        @if (empty($wiki['page']['data']['state']))<h3>{{ $lang['page']['discuss'] }}</h3>@endif
        <ul class="lists">
            @php
                $cnt = count($wiki['page']['data']['thread_list'] ?? []);
            @endphp
            @for ($i=0; $i < $cnt; ++$i)<li>
                <a href="#s-{{ $i+1 }}">{{ $i+1 }}</a>.
                <a href="/thread/{{ $wiki['page']['data']['thread_list'][$i]['slug'] }}">{{ $wiki['page']['data']['thread_list'][$i]['topic'] }}</a>
            </li>@endfor
        </ul>
        @if (empty($wiki['page']['data']['state']))
            <p><a href="/discuss/{{ rawurlencode((string) ($uri_data['title'])) }}?state=close">[{{ $lang['show_closed_discuss'] }}]</a></p>
            @for ($i=0; $i<$cnt; ++$i)<div>
                <h2>{{ $i+1 }}. <a id="s-{{ $i+1 }}" href="/thread/{{ $wiki['page']['data']['thread_list'][$i]['slug'] }}">{{ $wiki['page']['data']['thread_list'][$i]['topic'] }}</a></h2>
                <div class="t">@foreach ($wiki['page']['data']['thread_list'][$i]['discuss'] as $d)
                    @if ($d['admin'])
                        @php
                            $str = 'data-pressdo-user-member';
                        @endphp
                    @else
                        @php
                            $str = '';
                        @endphp
                    @endif
                    @if ($d['type'] == 'comment')
                        @php
                            $sta = '';
                        @endphp
                    @else
                        @php
                            $sta = 's';
                        @endphp
                    @endif

                    @if ($d['id'] == ($wiki['page']['data']['thread_list'][$i]['discuss'][1]['id'] ?? null) && $d['id'] > 2)
                        <a href="/thread/{{ $wiki['page']['data']['thread_list'][$i]['slug'] }}" class="thread m">more...</a>
                    @endif
                    <div class="thread r v">
                        <div class="thread vi">
                            <div class="thread c">
                                <div class="thread h">
                                    <span><a id="{{ $d['id'] }}">#{{ $d['id'] }}</a></span>
                                    <div v="inline">
                                        <div v="inline-block">
                                            <a {{ $str }}>{{ ($d['author'] ?? '').($d['ip'] ?? '') }}</a>
                                        </div>
                                    </div>
                                    <span class="d">{!! ViewTime::absolute((int) ($d['date'] ?? 0)) !!}</span>
                                </div>
                                <div class="thread b {{ $sta }}">
                                    <div>
                                        <div class="thread w">
                                            <div data-pressdo-doc-paragraph>
                                            @if ($d['type'] == 'comment')
                                                {!! $d['text'] !!}
                                            @else
                                                {!! sprintf($lang['msg']['changed_thread_'.$d['type']], e($d['text'] ?? '')) !!}
                                            @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach</div>
            </div>@endfor
        @endif
    @endif
    @if (empty($wiki['page']['data']['state']))
    <h3>{{ $lang['thread']['create_thread'] }}</h3>
    @if ($uri_data['title'] == $config['wiki.front_page'])<div class="a">
        <strong>{{ $lang['msg']['warning'] }}</strong>
        {{ sprintf($lang['msg']['discuss_in_frontpage'], $config['wiki.front_page']) }}
    </div>@endif
    <form method="POST" id="editForm">
        <div data-pressdo-loginform class="c">
            <label data-pressdo-breakline for="topicInput">{{ $lang['thread']['topic'] }} :</label>
            <input data-pressdo-thread data-pressdo-wideinput type="text" id="topicInput" name="topic">
        </div>
        <div data-pressdo-loginform class="c">
            <label data-pressdo-breakline for="contentInput">{!! $lang['thread']['content'] !!} :</label>
            <textarea data-pressdo-thread name="text" id="contentInput" rows="5"></textarea>
            @if (empty($wiki['session']['member']['username']))<p class="unlogined">{{ sprintf($lang['msg']['unlogined_create'], $wiki['session']['ip']) }}</p>@endif
        </div>
        <button type="submit" class="pressdo-btn btn-wideright btn-blue">{{ $lang['submit'] }}</button>
        <div class="{{ $api_config['captcha_class_name'] ?? '' }}" data-sitekey="{{ $config['captcha.key'] ?? '' }}" data-callback="onCaptchaSubmit" data-execution="execute" data-size="invisible"></div>
        <div style="clear: both;"></div>
    </form>
    @endif
</div>
