@php
    use PressDo\App\Infrastructure\Rendering\ViewTime;

    $logTypes = ['all', 'create', 'delete', 'move', 'revert'];
    $logType = $query->queryString('logtype');
    $idset = [];
    if (!in_array($logType, $logTypes, true)) {
        $logType = 'all';
    }
@endphp

<div class="top-area">
    <div class="toolkit-wrap">
        <div class="menu-wrap">
            <ul class="recent-toolbox">
                @foreach ($logTypes as $type)
                    <li><a @class(['recent-menu-current' => $logType === $type, 'recent-menu']) href="/RecentChanges?logtype={{ $type }}">{{ $lang['recent']['logtype_'.$type] }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
<div class="recent-changes recent-table">
    <div class="recent-tr recent-th">
        <div class="recent-td">{{ $lang['recent']['document'] }}</div>
        <div class="recent-td">{{ $lang['recent']['function'] }}</div>
        <div class="recent-td">{{ $lang['recent']['editor'] }}</div>
        <div class="recent-td">{{ $lang['recent']['ago'] }}</div>
    </div>
    @foreach ($wiki['page']['data']['content'] as $he)
        @php
            $diff = 'a';
            $disdiff = false;
            $Diff = $he['count'];
        @endphp
        @if ($he['document']['forceShowNamespace'] === false)
            @php
                $title = $he['document']['title'];
            @endphp
        @else
            @php
                $title = $he['document']['namespace'].':'.$he['document']['title'];
            @endphp
        @endif


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

        <div class="recent-tr">
            <div class="recent-td">
                <a href="/w/{{ rawurlencode((string) ($title)) }}">{{ $title }}</a>
                <span class="recent-history-length history-{{ $color }}">{{ $flag.$Diff }}</span>
            </div>
            <div class="recent-td recent-tool">
                <div class="recent-buttonarea">
                    <a class="pressdo-btn btn-round" role="button" href="/history/{{ rawurlencode((string) ($title)) }}" title="역사">역사</a>
                    <{{ ($diff === 'span' ? 'span' : 'a') }} role="button" @disabled($disdiff) class="pressdo-btn btn-round" href="/diff/{{ rawurlencode((string) ($title)) }}?uuid={{ $he['uuid'] }}" title="비교">비교</{{ ($diff === 'span' ? 'span' : 'a') }}>
                    <a class="pressdo-btn btn-round" role="button" href="/discuss/{{ rawurlencode((string) ($title)) }}" title="토론">토론</a>
                </div>
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
</div>
@include('partials.userpopper', ['idset' => $idset])
