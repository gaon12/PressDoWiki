@if ($wiki['page']['data']['state'] == 'close')<ul class="lists">
    @foreach ($wiki['page']['data']['thread_list'] as $i => $t)<li> {{ $i + 1 }}. <a href="/thread/{{ $t['urlstr'] }}">{{ $t['topic'] }}</a></li>@endforeach
</ul>@endif
@if ($wiki['page']['data']['state'] == 'closed_edit_requests')<ul class="lists">
    @foreach ($wiki['page']['data']['thread_list'] as $i => $t)<li> {{ $i + 1 }}. <a href="/edit_request/{{ $t['urlstr'] }}">{{ $lang['page']['edit_request'].$t['urlstr'] }}</a></li>@endforeach
</ul>@endif
