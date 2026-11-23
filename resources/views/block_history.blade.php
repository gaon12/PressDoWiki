@php
    use PressDo\App\Infrastructure\Rendering\ViewTime;
@endphp

<form class="pagelist-menu search-form" method="get">
    <input type="hidden" name="page" value="BlockHistory">
    <select class="selfield" name="target" value="text">
        <option value="text">{{ $lang['acl']['text'] }}</option>
        <option value="author">{{ $lang['acl']['author'] }}</option>
    </select>
    <input class="textfield" type="text" name="query" placeholder="{{ $lang['page']['Search'] }}">
    <div>
        <button class="pressdo-btn btn-blue" type="submit">{{ $lang['page']['Search'] }}</button>
        <a role="button" class="pressdo-btn" href="/BlockHistory">{{ $lang['btn']['reset'] }}</a>
    </div>
</form>
@if (!$wiki['page']['data']['prev_page'])
    @php
        $phref = null;
    @endphp
@else
    @php
        $phref = '/BlockHistory?until='.$wiki['page']['data']['prev_page'];
    @endphp
@endif
@if (!$wiki['page']['data']['next_page'])
    @php
        $nhref = null;
    @endphp
@else
    @php
        $nhref = '/BlockHistory?from='.$wiki['page']['data']['next_page'];
    @endphp
@endif
@include('partials.pagination', ['phref' => $phref, 'nhref' => $nhref])
@php
    $idset = [];
@endphp
<ul class="list-table">
    @foreach ($wiki['page']['data']['history'] as $h)<li>
        <div>
            <span @class(['bh-green' => $h['action'] == 'aclgroup_add', 'bh-grey' => $h['action'] == 'aclgroup_remove', 'bh-orange' => $h['action'] == 'grant', 'bh-purple' => $h['action'] == 'login_history', 'bh-blue' => $h['action'] == 'batch_revert', 'list-icon'])><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                @if ($h['action'] == 'aclgroup_add')<path d="M96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM0 482.3C0 383.8 79.8 304 178.3 304h91.4C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7H29.7C13.3 512 0 498.7 0 482.3zM504 312V248H440c-13.3 0-24-10.7-24-24s10.7-24 24-24h64V136c0-13.3 10.7-24 24-24s24 10.7 24 24v64h64c13.3 0 24 10.7 24 24s-10.7 24-24 24H552v64c0 13.3-10.7 24-24 24s-24-10.7-24-24z"></path>@endif
                @if ($h['action'] == 'aclgroup_remove')<path d="M96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM0 482.3C0 383.8 79.8 304 178.3 304h91.4C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7H29.7C13.3 512 0 498.7 0 482.3zM472 200H616c13.3 0 24 10.7 24 24s-10.7 24-24 24H472c-13.3 0-24-10.7-24-24s10.7-24 24-24z"></path>@endif
                @if ($h['action'] == 'grant')<path d="M96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM0 482.3C0 383.8 79.8 304 178.3 304h91.4C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7H29.7C13.3 512 0 498.7 0 482.3zM625 177L497 305c-9.4 9.4-24.6 9.4-33.9 0l-64-64c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l47 47L591 143c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9z"></path>@endif
                @if ($h['action'] == 'login_history')<path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H418.3c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304H178.3z"></path>@endif
                @if ($h['action'] == 'batch_revert')<path d="M75 75L41 41C25.9 25.9 0 36.6 0 57.9V168c0 13.3 10.7 24 24 24H134.1c21.4 0 32.1-25.9 17-41l-30.8-30.8C155 85.5 203 64 256 64c106 0 192 86 192 192s-86 192-192 192c-40.8 0-78.6-12.7-109.7-34.4c-14.5-10.1-34.4-6.6-44.6 7.9s-6.6 34.4 7.9 44.6C151.2 495 201.7 512 256 512c141.4 0 256-114.6 256-256S397.4 0 256 0C185.3 0 121.3 28.7 75 75zm181 53c-13.3 0-24 10.7-24 24V256c0 6.4 2.5 12.5 7 17l72 72c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9l-65-65V152c0-13.3-10.7-24-24-24z"></path>@endif
            </svg></span>
        </div>
        <div class="list-table-content">
            <div>
                @if ($h['author'] !== null)
                    @php
                        $target_type = $lang['acl']['member'];
                        $subject = '<div class="context-menu v-popper v-popper--theme-contextmenu"><a class="user user-member" href="/w/'.rawurlencode('사용자:'.$h['author']).'" aria-describedby="contextmenu-'.e($h['author_uuid']).'">'.e($h['author']).'</a></div>';
                    @endphp
                @else
                    @php
                        $target_type = 'IP';
                        $subject = '<div class="context-menu v-popper v-popper--theme-contextmenu"><a class="user" href="/contribution/'.e($h['author_uuid']).'/document" aria-describedby="contextmenu-'.e($h['author_uuid']).'">'.e($h['author_ip']).'</a></div>';
                    @endphp
                @endif
                @php
                    $idset[$h['author_uuid']] = [$target_type, $h['author'].$h['author_ip']];
                @endphp
                @if ($h['content']['duration'] == 'forever')
                    @php
                        $timestr = $lang['msg']['b_forever'];
                    @endphp
                @elseif ($h['content']['duration'] == null)
                    @php
                        $timestr = '';
                    @endphp
                @else
                    @php
                        $timeSet = '';
                    @endphp
                    @foreach (array_keys($h['content']['duration']) as $t)
                        @if ($h['content']['duration'][$t] != 0)
                            @php
                                $timeSet = $timeSet.' '.$h['content']['duration'][$t].$lang['acl'][$t];
                            @endphp
                        @endif
                    @endforeach
                    @php
                        $timestr = sprintf($lang['msg']['b_for'], $timeSet);
                    @endphp
                @endif


                    @if (in_array($h['action'], ['aclgroup_add', 'aclgroup_remove'], true))
                        @php
                            $msg = sprintf($lang['msg']['b_'.$h['action']], '<b>'.e($h['content']['aclgroup']).'</b>', $timestr);
                            $hid = '#'.$h['content']['id'];
                        @endphp
                    @elseif (in_array($h['action'], ['login_history', 'grant'], true))
                        @php
                            $msg = $lang['msg']['b_'.$h['action']];
                            $hid = '';
                        @endphp
                @endif

                @if (($h['action'] == 'aclgroup_add' || $h['action'] == 'aclgroup_remove') && $h['content']['ip'] || $h['action'] == 'login_history')
                    @php
                        $target = $h['content']['ip'] ?? $h['content']['member'];
                    @endphp
                @else
                    @if ($h['content']['ip'])
                        @php
                            $idset[$h['content']['target_uuid']] = ['IP', $h['content']['ip']];
                            $target = '<div class="context-menu v-popper v-popper--theme-contextmenu"><a class="user" href="/contribution/'.$h['content']['target_uuid'].'/document" aria-describedby="contextmenu-'.$h['content']['target_uuid'].'">'.$h['content']['ip'].'</a></div>';
                        @endphp
                    @else
                        @php
                            $idset[$h['content']['target_uuid']] = [$lang['acl']['member'], $h['content']['member']];
                            $target = '<div class="context-menu v-popper v-popper--theme-contextmenu"><a class="user user-member" href="/w/'.rawurlencode('사용자:'.$h['content']['member']).'" aria-describedby="contextmenu-'.$h['content']['target_uuid'].'">'.$h['content']['member'].'</a></div>';
                        @endphp
                    @endif
                @endif

                {!! sprintf($lang['msg']['b_user'], $subject, e($target), e($target_type), $msg) !!}
                <span class="bh-id">{{ $hid }}</span>
            </div>
            <div class="recent-log">
                @if ($h['action'] == 'grant')<span>{{ $h['content']['granted'] }}</span>@endif
            </div>
            <div class="recent-log">
                {!! ViewTime::absolute((int) ($h['datetime'] ?? 0)) !!}
                @if ($h['action'] !== 'login_history' && $h['action'] !== 'grant')<span>({{ $h['content']['memo'] }})</span>@endif
            </div>
        </div>
    </li>@endforeach
</ul>
@include('partials.pagination', ['phref' => $phref, 'nhref' => $nhref])
@include('partials.userpopper', ['idset' => $idset])
