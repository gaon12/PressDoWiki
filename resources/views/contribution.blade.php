@php
    use PressDo\App\Infrastructure\Rendering\ViewTime;
@endphp

<div class="top-area">
    <div class="toolkit-wrap">
        <div class="menu-wrap">
            <ul class="recent-toolbox">
                <li><a @class(['recent-menu-current' => $uri_data['type'] == 'document', 'recent-menu']) href="/contribution/{{ $uri_data['target'] }}/document">{{ $lang['recent']['document'] }}</a></li>
                <li><a @class(['recent-menu-current' => $uri_data['type'] == 'discuss', 'recent-menu']) href="/contribution/{{ $uri_data['target'] }}/discuss">{{ $lang['page']['discuss'] }}</a></li>
                <li><a @class(['recent-menu-current' => $uri_data['type'] == 'edit_request', 'recent-menu']) href="/contribution/{{ $uri_data['target'] }}/edit_request">{{ $lang['page']['edit_request'] }}</a></li>
            </ul>
        </div>
    </div>
</div>
@if ($uri_data['type'] == 'discuss')<div>{{ $lang['recent']['recent_30_days'] }}</div>
@else
<div>{{ sprintf($lang['recent']['total_contribution'], $wiki['page']['data']['count']) }}</div>@endif
@if (!$wiki['page']['data']['until'])
    @php
        $phref = null;
    @endphp
@else
    @php
        $phref = '/contribution/'.rawurlencode($uri_data['target']).'/'.rawurlencode($uri_data['type']).'?until='.$wiki['page']['data']['until'];
    @endphp
@endif
@if (!$wiki['page']['data']['from'])
    @php
        $nhref = null;
    @endphp
@else
    @php
        $nhref = '/contribution/'.rawurlencode($uri_data['target']).'/'.rawurlencode($uri_data['type']).'?from='.$wiki['page']['data']['from'];
    @endphp
@endif
@include('partials.pagination', ['phref' => $phref, 'nhref' => $nhref])
@if ($uri_data['type'] == 'document')<div class="recent-changes recent-table">
    <div class="contr-tr recent-th">
        <div class="recent-td">{{ $lang['recent']['document'] }}</div>
        <div class="recent-td">{{ $lang['recent']['function'] }}</div>
        <div class="recent-td">{{ $lang['recent']['ago'] }}</div>
    </div>
    @foreach ($wiki['page']['data']['content'] as $he)
        @php
            $diff = 'a';
            $disdiff = false;
            $Diff = $he['count'];
            $title = \PressDo\App\Core\Controller::makeTitle($he['document']['namespace'], $he['document']['title']);
        @endphp
        @if ($Diff > 0)
            @php
                $color = 'green';
                $flag = '+';
            @endphp
        @elseif ($Diff == 0)
            @php
                $color = 'gray';
                $flag = '';
            @endphp
        @elseif ($Diff < 0)
            @php
                $color = 'red';
                $flag = '';
            @endphp
        @endif

        @if ($he['rev'] == 1)
            @php
                $diff = 'span';
                $disdiff = true;
            @endphp
        @endif

        <div class="contr-tr">
            <div class="recent-td">
                <a href="/w/{{ rawurlencode((string) ($title)) }}">{{ $title }}</a>
                <span class="recent-document">(<a class="recent-rev" href="/w/{{ rawurlencode((string) ($title)) }}?uuid={{ $he['uuid'] }}">r{{ $he['rev'] }}</a>)</span>
                <span class="recent-history-length history-{{ $color }}">{{ $flag.$Diff }}</span>
            </div>
            <div class="recent-td recent-tool">
                <div class="recent-buttonarea">
                    <a class="pressdo-btn btn-round" role="button" href="/history/{{ rawurlencode((string) ($title)) }}" title="역사">역사</a>
                    <{{ ($diff === 'span' ? 'span' : 'a') }} role="button" @disabled($disdiff) class="pressdo-btn btn-round" href="/diff/{{ rawurlencode((string) ($title)) }}?uuid={{ $he['uuid'] }}" title="비교">비교</{{ ($diff === 'span' ? 'span' : 'a') }}>
                    <a class="pressdo-btn btn-round" role="button" href="/discuss/{{ rawurlencode((string) ($title)) }}" title="토론">토론</a>
                </div>
            </div>
            <div class="recent-td recent-datetime">{!! ViewTime::relative((int) ($he['date'] ?? 0)) !!}</div>
            @if (mb_strlen((string) ($he['log'] ?? '')) > 0 || $he['logtype'] !== 'modify')<div class="recent-td recent-log">
                @if (trim((string) ($he['log'])) !== '')<span class="recent-changes">{{ $he['log'] }}</span>@endif
                <i>

                    @if ($he['logtype'] === 'revert')
                        ({{ sprintf($lang['log']['revert'], $he['target_rev']) }})
                    @elseif (in_array($he['logtype'], ['delete', 'create'], true))
                        ({{ $lang['log'][$he['logtype']] }})
                    @elseif ($he['logtype'] === 'move')
                        ({{ sprintf($lang['log']['move'], $he['from'], $he['to']) }})
                    @elseif ($he['logtype'] === 'acl')
                        ({{ sprintf($lang['log']['acl'], $he['acl']) }})
                @endif
                </i>
            </div>@endif
        </div>
    @endforeach
</div>@endif
@if ($uri_data['type'] == 'discuss')<div class="recent-changes recent-table">
    <div class="contr-tr recent-th">
        <div class="recent-td">{{ $lang['recent']['title'] }}</div>
        <div class="recent-td">{{ $lang['recent']['ago'] }}</div>
    </div>
    @foreach ($wiki['page']['data']['content'] as $he)
        @if ($he['document']['forceShowNamespace'] === false)
            @php
                $ns = '';
            @endphp
        @else
            @php
                $ns = $he['document']['namespace'].':';
            @endphp
        @endif
        <div class="contr-tr">
            <div class="recent-td">
                <a href="/thread/{{ $he['slug'] }}#{{ $he['no'] }}">#{{ $he['no'] }} {{ $he['topic'] }}</a>
                <span class="recent-document">
                    <span class="recent-document-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M64 464c-8.8 0-16-7.2-16-16V64c0-8.8 7.2-16 16-16H224v80c0 17.7 14.3 32 32 32h80V448c0 8.8-7.2 16-16 16H64zM64 0C28.7 0 0 28.7 0 64V448c0 35.3 28.7 64 64 64H320c35.3 0 64-28.7 64-64V154.5c0-17-6.7-33.3-18.7-45.3L274.7 18.7C262.7 6.7 246.5 0 229.5 0H64zm56 256c-13.3 0-24 10.7-24 24s10.7 24 24 24H264c13.3 0 24-10.7 24-24s-10.7-24-24-24H120zm0 96c-13.3 0-24 10.7-24 24s10.7 24 24 24H264c13.3 0 24-10.7 24-24s-10.7-24-24-24H120z"></path></svg></span>
                </span>
                <a class="recent-document-title" href="/discuss/{{ rawurlencode((string) ($ns.$he['document']['title'])) }}">{{ $ns.$he['document']['title'] }}</a>
            </div>
            <div class="recent-td recent-datetime">{{ ViewTime::relative((int) ($he['date'] ?? 0)) }}</div>
        </div>
    @endforeach
</div>@endif
@include('partials.pagination', ['phref' => $phref, 'nhref' => $nhref])
