@php
    use PressDo\App\Infrastructure\Rendering\ViewTime;
@endphp

<div>
    @if (in_array($wiki['page']['view_name'], ['RandomPage', 'NeededPages', 'OrphanedPages', 'UncategorizedPages']))<form class="pagelist-menu" method="get">
        @php
            $ns = '?namespace='.rawurlencode($wiki['page']['data']['namespace']).'&';
        @endphp
        <label data-pressdo-pagelist>{{ $lang['namespace'] }} :</label><select name="namespace">
            @foreach ($namespace as $n)<option @selected(($n == $wiki['page']['data']['namespace'])) value="{{ $n }}">{{ $n }}</option>@endforeach
        </select>
        <button type="submit" class="pressdo-btn btn-blue">{{ $lang['submit'] }}</button>
    </form>@endif
    @php
        $ns ??= '?';
    @endphp
    <p>{{ $lang['msg'][$wiki['page']['view_name']] }}</p>
        @if (empty($wiki['page']['data']['prev_ver']))
            @php
                $phref = null;
            @endphp
        @else
            @php
                $phref = '/'.$wiki['page']['view_name'].$ns.'until='.$wiki['page']['data']['prev_ver'];
            @endphp
        @endif
        @if (empty($wiki['page']['data']['next_ver']))
            @php
                $nhref = null;
            @endphp
        @else
            @php
                $nhref = '/'.$wiki['page']['view_name'].$ns.'from='.$wiki['page']['data']['next_ver'];
            @endphp
        @endif
    @include('partials.pagination', ['phref' => $phref, 'nhref' => $nhref])
    <ul class="lists">
        @foreach ($wiki['page']['data']['content'] as $d)
            @if ($d['forceShowNamespace'] === false)
                @php
                    $title = $d['title'];
                @endphp
            @else
                @php
                    $title = $d['namespace'].':'.$d['title'];
                @endphp
            @endif


                @if ($wiki['page']['view_name'] === 'OldPages')
                    <li><a href="/w/{{ rawurlencode((string) ($title)) }}">{{ $title }}</a> ({{ $lang['document']['modifiedat'] }}:{!! ViewTime::absolute((int) ($d['dt'] ?? 0)) !!})</li>
                @elseif (in_array($wiki['page']['view_name'], ['LongestPages', 'ShortestPages'], true))
                    <li><a href="/w/{{ rawurlencode((string) ($title)) }}">{{ $title }}</a> ({{ $d['len'].$lang['letters'] }})</li>
                @elseif ($wiki['page']['view_name'] === 'NeededPages')
                    <li><a href="/w/{{ rawurlencode((string) ($title)) }}">{{ $title }}</a> <a href="/backlink/{{ rawurlencode((string) ($title)) }}">[{{ $lang['btn']['backlink'] }}]</a></li>
                @elseif (in_array($wiki['page']['view_name'], ['RandomPage', 'UncategorizedPages', 'OrphanedPages'], true))
                    <li><a href="/w/{{ rawurlencode((string) ($title)) }}">{{ $title }}</a></li>
            @endif
        @endforeach
    </ul>
    @include('partials.pagination', ['phref' => $phref, 'nhref' => $nhref])
</div>
