<form class="spacing-ud" method="GET">
    <select class="search" name="namespace">
        <option value="">{{ $lang['recent']['logtype_all'] }}</option>
        @foreach ($namespace as $n)<option value="{{ $n }}" @selected($wiki['page']['data']['body']['namespace'] === $n)>{{ $n }}</option>@endforeach
    </select>
    <select class="search" name="target">
        <option @selected($wiki['page']['data']['body']['target'] == 'title_content') value="title_content">{{ $lang['search']['title_content'] }}</option>
        <option @selected($wiki['page']['data']['body']['target'] == 'title') value="title">{{ $lang['search']['title'] }}</option>
        <option @selected($wiki['page']['data']['body']['target'] == 'content') value="content">{{ $lang['search']['content'] }}</option>
        <option @selected($wiki['page']['data']['body']['target'] == 'raw') value="raw">{{ $lang['search']['raw'] }}</option>
    </select>
    <input class="search" type="text" name="q" value="{{ $wiki['page']['data']['body']['query'] }}">
    <button type="submit" class="pressdo-btn-nonflex btn-lightblue">{{ $lang['btn']['search'] }}</button>
</form>
<div class="a-mono search">
    <div>
        <div class="l">
            <i class="ion-ios-arrow-forward"></i>{{ $lang['msg']['search_go_directly'] }}
        </div>
        <div class="r">
            <a class="pressdo-btn-nonflex" href="/w/{{ rawurlencode($wiki['page']['data']['body']['query']) }}">{{ sprintf($lang['search']['go'], $wiki['page']['data']['body']['query']) }}</a>
        </div>
        <div class="clearfix"></div>
    </div>
</div>
<div class="s">
    {{ sprintf($lang['search']['count'], $wiki['page']['data']['resultCount']) }} / {{ sprintf($lang['search']['period'], $wiki['page']['data']['period']) }}
</div>
<section class="search">
    @foreach ($wiki['page']['data']['result'] as $r)<div>
        @if ($r['document']['forceShowNamespace'] === false)
            @php
                $title = $r['document']['title'];
            @endphp
        @else
            @php
                $title = $r['document']['namespace'].':'.$r['document']['title'];
            @endphp
        @endif
        <h4>
            <i class="ion-md-document"></i>
            <a href="/w/{{ rawurlencode((string) ($title)) }}">{{ $title }}</a>
        </h4>
        <div>
            {!! $r['content'] !!}
        </div>
    </div>@endforeach
    <nav class="pages-nums">
        <ul>
            <li><a href="/Search?">1</a></li>
        </ul>
    </nav>
    <div class="clearfix"></div>
</section>
