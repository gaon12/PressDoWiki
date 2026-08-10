@php
    $data = $wiki['page']['data'] ?? [];
    $document = $data['document'] ?? [];
    $categories = $document['categories'] ?? [];
    $userData = $data['userData'] ?? null;
    $block = is_array($userData) ? ($userData['block'] ?? null) : null;
@endphp

@if (!empty($error['errbox']))
    <div class="a e" role="alert">
        <strong>{{ $lang['msg']['error'] ?? '오류' }}</strong>
        <span>{{ $error['message'] ?? '' }}</span>
    </div>
@endif

@if (!empty($alert['alertbox']))
    <div class="a" role="status">
        <strong>{{ $lang['msg']['alert'] ?? '알림' }}</strong>
        <span>{{ $alert['message'] ?? '' }}</span>
    </div>
@endif

@if (is_array($categories) && $categories !== [])
    <nav id="categoryspace_top" class="wiki-categories" aria-label="문서 분류">
        <span>분류</span>
        <ul>
            @foreach ($categories as $category => $classes)
                <li>
                    <a href="/w/{{ rawurlencode('분류:'.(string) $category) }}" class="{{ is_array($classes) ? implode(' ', $classes) : '' }}">
                        {{ $category }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif

<div class="w">
    @if (!empty($data['user']) && is_array($userData))
        @if (($userData['admin'] ?? false) === true)
            <div class="wiki-user-notice wiki-user-admin">
                <span>{{ $lang['msg']['this_is_admin'] ?? '이 사용자는 특수 권한을 가지고 있습니다.' }}</span>
            </div>
        @endif

        @if (is_array($block) && ($block['blocked'] ?? false) === true)
            @php
                $blockedAt = (int) ($block['datetime'] ?? 0);
                $until = (string) ($block['until'] ?? '0') === '0'
                    ? ($lang['msg']['b_forever'] ?? '영구적으로')
                    : sprintf((string) ($lang['until'] ?? '%s 까지'), (string) $block['until']);
            @endphp
            <div class="wiki-user-notice wiki-user-blocked">
                <span>{{ $lang['msg']['this_is_blocked'] ?? '이 사용자는 차단된 사용자입니다.' }} (#{{ $block['seq'] ?? '' }})</span>
                <br><br>
                <span>{{ sprintf((string) ($lang['msg']['this_is_blocked_user'] ?? '이 사용자는 %1$s에 %2$s 차단되었습니다.'), date('Y-m-d H:i:s', $blockedAt), $until) }}</span>
                <br>
                <span>{{ $lang['blocked_reason'] ?? '차단 사유' }}: {{ $block['memo'] ?? '' }}</span>
            </div>
        @endif
    @endif

    @if (($document['namespace'] ?? null) === '파일')
        @php
            $fileTitle = ($document['forceShowNamespace'] ?? null) !== false
                ? '파일:'.(string) ($document['title'] ?? '')
                : (string) ($document['title'] ?? '');
        @endphp
        <span class="wiki-image-align-normal">
            <span class="wiki-image-wrapper">
                <img
                    class="wiki-image"
                    src="{{ (string) ($config['storage.host'] ?? '').(string) ($data['file_endpoint'] ?? '') }}"
                    alt="{{ $fileTitle }}"
                    loading="lazy"
                    decoding="async"
                >
            </span>
        </span>
    @endif

    {{-- Markup engines sanitize and render this HTML before it crosses the view boundary. --}}
    {!! (string) ($document['content'] ?? '') !!}

    <div class="popper">
        <div class="popper__arrow"></div>
        <div class="popper__inner"></div>
    </div>
</div>

@foreach (($data['category_documents'] ?? []) as $namespace => $categoryGroup)
    @continue(!is_array($categoryGroup))
    <section class="subcategories">
        <h2>
            {{ $namespace === '분류'
                ? ($lang['category']['subcategories'] ?? '하위 분류')
                : sprintf((string) ($lang['category']['sub_something'] ?? '"%1$s" 분류에 속하는 %2$s'), (string) ($document['title'] ?? ''), (string) $namespace) }}
        </h2>
        <div>
            <div>{{ sprintf((string) ($lang['category']['count'] ?? '전체 %s개 문서'), (string) ($categoryGroup['count'] ?? 0)) }}</div>
            @foreach ($categoryGroup as $heading => $entries)
                @continue($heading === 'count' || !is_array($entries))
                <h3>{{ $heading }}</h3>
                <ul class="lists">
                    @foreach ($entries as $entry)
                        @continue(!is_array($entry) || !is_array($entry['document'] ?? null))
                        @php
                            $entryDocument = $entry['document'];
                            $entryTitle = ($entryDocument['forceShowNamespace'] ?? null) !== false
                                ? (string) ($entryDocument['namespace'] ?? $namespace).':'.(string) ($entryDocument['title'] ?? '')
                                : (string) ($entryDocument['title'] ?? '');
                        @endphp
                        <li class="backlinks">
                            <a href="/w/{{ rawurlencode($entryTitle) }}">{{ $entryTitle }}</a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </section>
@endforeach
