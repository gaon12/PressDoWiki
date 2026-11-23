@php
    use PressDo\App\Infrastructure\Rendering\ViewTime;
@endphp

@php
    $docPath = rawurlencode($uri_data['title']);
    $data = $wiki['page']['data'];
    $phref = isset($data['prev_ver']) ? '/history/' . $docPath . '?until=' . $data['prev_ver'] : null;
    $nhref = isset($data['next_ver']) ? '/history/' . $docPath . '?from=' . $data['next_ver'] : null;
    $until = $query->queryOptionalString('until');
    $latestListed = $data['history'][0]['rev'] ?? null;
    $from = $query->queryOptionalString('from')
        ?? ($until !== null && ctype_digit($until) && $latestListed !== null ? min((int) $until + 30, $latestListed) : null);
    $idset = [];
@endphp
<div class="history-menu">
    @include('partials.pagination', ['phref' => $phref, 'nhref' => $nhref])
    <form>
        <div class="history-menu-area history-goto page-btn-area">
            <input class="textfield" type="number" name="from" pattern="\d+" required value="{{ $from }}">
            <span>r</span>
            <button class="pressdo-btn" type="submit">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M307 34.8c-11.5 5.1-19 16.6-19 29.2l0 64-112 0C78.8 128 0 206.8 0 304C0 417.3 81.5 467.9 100.2 478.1c2.5 1.4 5.3 1.9 8.1 1.9c10.9 0 19.7-8.9 19.7-19.7c0-7.5-4.3-14.4-9.8-19.5C108.8 431.9 96 414.4 96 384c0-53 43-96 96-96l96 0 0 64c0 12.6 7.4 24.1 19 29.2s25 3 34.4-5.4l160-144c6.7-6.1 10.6-14.7 10.6-23.8s-3.8-17.7-10.6-23.8l-160-144c-9.4-8.5-22.9-10.6-34.4-5.4z"></path></svg>
            </button>
        </div>
    </form>
</div>
<form action="/diff/{{ rawurlencode((string) ($uri_data['title'])) }}" method="GET">
    <p><button class="pressdo-btn bt-white" type="submit">{{ $lang['history']['compare_revision'] }}</button></p>
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

            @php

                $menu = [];

            @endphp
            @if (!$d['marked_troll'])
                @php
                    array_push($menu, '<a href="/w/'.$docPath.'?uuid='.rawurlencode((string) $d['uuid']).'">'.$lang['history']['view'].'</a>');
                    array_push($menu, '<a href="/raw/'.$docPath.'?uuid='.rawurlencode((string) $d['uuid']).'">RAW</a>');
                @endphp
                @php
                    array_push($menu, '<a href="/revert/'.$docPath.'?uuid='.rawurlencode((string) $d['uuid']).'">'.$lang['history']['revert'].'</a>');
                @endphp
            @else
                @php
                    array_push($menu, '<a href="/raw/'.$docPath.'?uuid='.rawurlencode((string) $d['uuid']).'">RAW</a>');
                @endphp
            @endif

            @if ($d['rev'] > 1)
                @php
                    array_push($menu, '<a href="/diff/'.$docPath.'?uuid='.rawurlencode((string) $d['uuid']).'">'.$lang['history']['diff'].'</a>');
                @endphp
            @endif

            @if (empty($d['author']) && $d['ip'] == $wiki['session']['ip'] && !empty($wiki['session']['member']['username']))
                @php
                    array_push($menu, '<a href="/move_ip/'.$docPath.'?uuid='.rawurlencode((string) $d['uuid']).'">'.$lang['history']['move_ip'].'</a>');
                @endphp
            @endif

            @if ($wiki['page']['data']['can_mark_troll'])
                @php
                    array_push($menu, '<a href="/mark_troll/'.$docPath.'?uuid='.rawurlencode((string) $d['uuid']).'">[A]'.($d['marked_troll'] ? $lang['history']['unmark_troll_revision'] : $lang['history']['mark_troll_revision']).'</a>');
                @endphp
            @endif

            @if ($wiki['page']['data']['can_hide_log'] && !empty($d['log']) && !$d['marked_troll'])
                @php
                    array_push($menu, '<a href="/hide_log/'.$docPath.'?uuid='.rawurlencode((string) $d['uuid']).'">[A]'.($d['log_hidden'] ? $lang['history']['unhide_document_history_log'] : $lang['history']['hide_document_history_log']).'</a>');
                @endphp
            @endif

            @if ($wiki['page']['data']['can_hide_revision'] && !$d['marked_troll'])
                @php
                    array_push($menu, '<a href="/hide_revision/'.$docPath.'?uuid='.rawurlencode((string) $d['uuid']).'">[A]'.($d['revision_hidden'] ? $lang['history']['unhide_revision'] : $lang['history']['hide_revision']).'</a>');
                @endphp
            @endif

            <li @class(['history-troll' => $d['marked_troll'], 'history'])>{!! ViewTime::absolute((int) ($d['date'] ?? 0)) !!}
                <span class="history-btns">({!! implode(' | ', $menu) !!})
                </span>
                <span><input type="radio" name="olduuid" value="{{ $d['uuid'] }}" v="inline-block"><input type="radio" name="uuid" value="{{ $d['uuid'] }}" v="inline-block"></span>
                <i>
                    @if ($d['logtype'] === 'revert')
                        ({{ sprintf($lang['log']['revert'], $d['target_rev']) }})
                    @elseif (in_array($d['logtype'], ['delete', 'create'], true))
                        ({{ $lang['log'][$d['logtype']] }})
                    @elseif ($d['logtype'] === 'move')
                        ({{ sprintf($lang['log']['move'], $d['from'], $d['to']) }})
                    @elseif ($d['logtype'] === 'acl')
                        ({{ sprintf($lang['log']['acl'], $d['acl']) }})
                @endif</i>
                <strong>r{{ $d['rev'] }}</strong> <span>(<span class="history-{{ $color }}">{{ $flag.$d['count'] }}</span>)</span>
                @if (!empty($d['edit_request']) && !$d['marked_troll'])<a href="/edit_request/{{ $d['edit_request'] }}"><i>({{ $lang['page']['edit_request'] }})</i>&nbsp;</a>@endif
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
                @if ($d['marked_troll'])
                    @php
                        $div = '<div class="context-menu v-popper v-popper--theme-contextmenu"><a style="'.e($d['style']).'" href="/w/'.rawurlencode('사용자:'.$d['marked_troll']['username']).'" class="user user-member" aria-describedby="contextmenu-'.e($d['marked_troll']['uuid']).'">'.e($d['marked_troll']['username']).'</a></div>';
                        $idset[$d['marked_troll']['uuid']] = [$lang['acl']['member'].($d['admin'] ? ' ('.$lang['perm']['admin'].')':''), $d['marked_troll']['username']];
                    @endphp
                    [{!! sprintf($lang['history']['marked_troll'], $div) !!}]
                @else
                    (<span class="history-gray">{{ $d['log'] }}</span>)
                @endif
            </li>
        @endforeach
    </ul>
    @include('partials.pagination', ['phref' => $phref, 'nhref' => $nhref])
</form>
@include('partials.userpopper', ['idset' => $idset])
