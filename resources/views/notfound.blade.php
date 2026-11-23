@php
    use PressDo\App\Infrastructure\Rendering\ViewTime;
@endphp

@if (!empty($alert['alertbox']))<div class="a">
    <strong>{{ $lang['msg']['alert'] }}</strong>
    <span>{{ $alert['message'] }}</span>
</div>@endif
<p>{{ $lang['msg']['document_not_found'] }}</p>
<p>
    <a href="/edit/{{ rawurlencode((string) ($uri_data['title'])) }}">[{{ $lang['document']['create_document'] }}]</a>
</p>
<div style="margin-top: 40px; margin-bottom: 15px;"></div>
@if (!empty($wiki['page']['data']['history']))
    <h3>{{ $lang['history']['history_of_doc'] }}</h3>
    <ul class="lists">
        @foreach ($wiki['page']['data']['history'] as $d)
            @if ($d['count'] > 0)
                @php
                    $color = 'green';
                    $flag = '+';
                @endphp
            @elseif ($d['count'] == 0)
                @php
                    $color = 'gray';
                    $flag = '';
                @endphp
            @elseif ($d['count'] < 0)
                @php
                    $color = 'red';
                    $flag = '';
                @endphp
            @endif
            <li class="history">{!! ViewTime::absolute((int) ($d['date'] ?? 0)) !!}
                <strong>r{{ $d['rev'] }}</strong> <i>
                    @if ($d['logtype'] === 'revert')
                        ({{ sprintf($lang['log']['revert'], $d['target_rev']) }})
                    @elseif (in_array($d['logtype'], ['delete', 'create'], true))
                        ({{ $lang['log'][$d['logtype']] }})
                    @elseif ($d['logtype'] === 'move')
                        ({{ sprintf($lang['log']['move'], $d['from'], $d['to']) }})
                    @elseif ($d['logtype'] === 'acl')
                        ({{ sprintf($lang['log']['acl'], $d['acl']) }})
                @endif</i><span>(<span class="history-{{ $color }}">{{ $flag.$d['count'] }}</span>)</span>
                <div class="context-menu v-popper v-popper--theme-contextmenu">
                    @if ($d['author'] === null)
                        @php
                            $idset[$d['contributor_uuid']] = ['IP', $d['ip']];
                        @endphp
                        <a style="{{ $d['style'] ?? '' }}" href="/contribution/{{ $d['contributor_uuid'] }}/document" class="user" aria-describedby="contextmenu-{{ $d['contributor_uuid'] }}">{{ $d['ip'] }}</a>
                    @else
                        @php
                            $idset[$d['contributor_uuid']] = [$lang['acl']['member'].($d['admin'] ? ' ('.$lang['perm']['admin'].')':''), $d['author']];
                        @endphp
                        <a style="{{ $d['style'] ?? '' }}" href="/w/{{ rawurlencode((string) ('사용자:'.$d['author'])) }}" class="user user-member" aria-describedby="contextmenu-{{ $d['contributor_uuid'] }}">{{ $d['author'] }}</a>
                    @endif
                </div>
                (<span class="history-gray">{{ $d['log'] }}</span>)
            </li>
        @endforeach
    </ul>
    <a href="/history/{{ rawurlencode((string) ($wiki['page']['title'])) }}">[{{ $lang['btn']['more'] }}]</a>
    @include('partials.userpopper', ['idset' => $idset])
@endif
