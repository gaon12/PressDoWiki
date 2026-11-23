@php
    use PressDo\App\Infrastructure\Rendering\ViewTime;

    $logTypes = ['normal_thread', 'old_thread', 'pause_thread', 'closed_thread', 'open_editrequest', 'accepted_editrequest', 'closed_editrequest', 'old_editrequest'];
    $logType = $query->queryString('logtype');
    $idset = [];
    if (!in_array($logType, $logTypes, true)) {
        $logType = 'normal_thread';
    }
@endphp

<div class="top-area">
    <div class="toolkit-wrap">
        <div class="menu-wrap">
            <ul class="recent-toolbox">
                @foreach ($logTypes as $type)
                    <li><a @class(['recent-menu-current' => $logType === $type, 'recent-menu']) href="/RecentDiscuss?logtype={{ $type }}">{{ $lang['recent']['logtype_'.$type] }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
<div class="recent-changes recent-table">
    <div class="contr-tr recent-th">
        <div class="recent-td">{{ $lang['recent']['title'] }}</div>
        <div class="recent-td">{{ $lang['recent']['editor'] }}</div>
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
                @if ($wiki['page']['data']['type'] == 'thread')<a href="/thread/{{ $he['slug'] }}">{{ $he['topic'] }}</a>@endif
                @if ($wiki['page']['data']['type'] == 'editrequest')
                    @if ($he['count'] > 0)
                        @php
                            $color = 'green';
                            $flag = '+';
                        @endphp
                    @elseif ($he['count'] == 0)
                        @php
                            $color = 'gray';
                            $flag = '';
                        @endphp
                    @elseif ($he['count'] < 0)
                        @php
                            $color = 'red';
                            $flag = '';
                        @endphp
                    @endif
                    <a href="/edit_request/{{ $he['slug'] }}">{{ $lang['page']['edit_request'] }} {{ $he['slug'] }}</a>
                    <span class="history-{{ $color }} recent-history-length">{{ $flag.$he['count'] }}</span>
                @endif
                <span class="recent-document">
                    <span class="recent-document-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M64 464c-8.8 0-16-7.2-16-16V64c0-8.8 7.2-16 16-16H224v80c0 17.7 14.3 32 32 32h80V448c0 8.8-7.2 16-16 16H64zM64 0C28.7 0 0 28.7 0 64V448c0 35.3 28.7 64 64 64H320c35.3 0 64-28.7 64-64V154.5c0-17-6.7-33.3-18.7-45.3L274.7 18.7C262.7 6.7 246.5 0 229.5 0H64zm56 256c-13.3 0-24 10.7-24 24s10.7 24 24 24H264c13.3 0 24-10.7 24-24s-10.7-24-24-24H120zm0 96c-13.3 0-24 10.7-24 24s10.7 24 24 24H264c13.3 0 24-10.7 24-24s-10.7-24-24-24H120z"></path></svg></span>
                </span>
                <span class="recent-document"><a class="recent-document-title" href="/discuss/{{ rawurlencode((string) ($ns.$he['document']['title'])) }}">{{ $ns.$he['document']['title'] }}</a></span>
            </div>
            <div class="recent-td">
                <span>
                    <div class="context-menu v-popper v-popper--theme-contextmenu">
                    @if ($he['author'] === null)
                        @php
                            $idset[$he['contributor_uuid']] = ['IP', $he['ip']];
                        @endphp
                        <a style="{{ $he['style'] }}" class="user" href="/contribution/{{ $he['contributor_uuid'] }}/document" class="user" aria-describedby="contextmenu-{{ $he['contributor_uuid'] }}">{{ $he['ip'] }}</a>
                    @else
                        @php
                            $idset[$he['contributor_uuid']] = [$lang['acl']['member'].($he['admin'] ? ' ('.$lang['perm']['admin'].')':''), $he['author']];
                        @endphp
                        <a style="{{ $he['style'] }}" class="user user-member" href="/w/{{ rawurlencode((string) ('사용자:'.$he['author'])) }}" class="user user-member" aria-describedby="contextmenu-{{ $he['contributor_uuid'] }}">{{ $he['author'] }}</a>
                    @endif
                    </div>
                </span>
            </div>
            <div class="recent-td recent-datetime">{!! ViewTime::relative((int) ($he['date'] ?? 0)) !!}</div>
        </div>
    @endforeach
</div>
@include('partials.userpopper', ['idset' => $idset])
