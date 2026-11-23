<form class="pagelist-menu" method="get">
    <label for="namespaceSelect">이름공간:</label>
    <select class="selfield" id="namespaceSelect" name="namespace" value="문서">@foreach ($wiki['page']['data']['backlink_count'] as $cnt)
        <option value="{{ $cnt['namespace'] }}" @selected(($query->queryOptionalString('namespace') ?? ($wiki['page']['data']['backlink_count'][0]['namespace'] ?? null)) == $cnt['namespace'])>{{ $cnt['namespace'] }} ({{ $cnt['count'] }})</option>
    @endforeach</select>
    <select class="selfield" name="flag" value="0">
        <option value="0" @selected((int) $query->queryString('flag') == 0)>(전체)</option>
        <option value="1" @selected((int) $query->queryString('flag') == 1)>link</option>
        <option value="2" @selected((int) $query->queryString('flag') == 2)>file</option>
        <option value="4" @selected((int) $query->queryString('flag') == 4)>include</option>
        <option value="8" @selected((int) $query->queryString('flag') == 8)>redirect</option>
    </select>
    <button type="submit" class="pressdo-btn btn-blue">제출</button>
</form>
<div>
    @if (empty($wiki['page']['data']['backlink']))
        {{ $lang['msg']['no_backlinks'] }}
    @endif
    @foreach ($wiki['page']['data']['backlink'] as $key => $val)<div>
        <h3>{{ $key }}</h3>
        <ul class="lists">@foreach ($val as $v)
            @if ($v['document']['forceShowNamespace'] !== false)
                @php
                    $ns = $v['document']['namespace'].':';
                @endphp
            @else
                @php
                    $ns = '';
                @endphp
            @endif
            <li class="backlinks"><a href="/w/{{ rawurlencode($ns.$v['document']['title']) }}">{{ $ns.$v['document']['title'] }}</a> ({{ $v['type'] }})</li>
        @endforeach</ul>
    </div>@endforeach
</div>
