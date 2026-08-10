@php
    $isLoggedIn = !empty($wiki['session']['member'] ?? null);
    $username = $wiki['session']['member']['username'] ?? null;
    $siteName = $config['wiki.site_name'] ?? '';
    $frontPage = $config['wiki.front_page'] ?? '';
    $docTitle = ($uri_data['title'] ?? '') !== '' ? ($uri_data['title'] ?? null) : null;
    $pageType = $uri_data['page'] ?? '';
    $docPageTypes = [
        'wiki', 'edit', 'history', 'discuss', 'diff', 'backlink',
        'move', 'delete', 'acl', 'raw', 'new_edit_request',
        'revert', 'hide_revision', 'hide_log', 'mark_troll', 'move_ip',
    ];
    $isDocPage = $docTitle !== null && in_array($pageType, $docPageTypes, true);
    $activeTab = match (true) {
        $pageType === 'discuss' => 'discuss',
        in_array($pageType, ['history', 'diff'], true) => 'history',
        in_array($pageType, ['edit', 'new_edit_request'], true) => 'edit',
        $isDocPage => 'wiki',
        default => null,
    };
    $encodedTitle = rawurlencode((string) ($docTitle ?? ''));
    $hasDiscussProgress = !empty($wiki['page']['data']['discuss_progress'] ?? false);
    $pageDisplayTitle = $wiki['page']['title'] ?? $docTitle ?? '';
@endphp

<div class="pd-wrapper">
    <header class="pd-header">
        <div class="pd-header-inner">
            <div class="pd-logo">
                <a href="/w/{{ rawurlencode((string) $frontPage) }}" class="pd-logo-link">
                    {{ $siteName ?: 'PressDoWiki' }}
                </a>
            </div>

            <form class="pd-search" action="/Search" method="get" autocomplete="off">
                <input
                    type="search"
                    name="q"
                    class="pd-search-input"
                    placeholder="{{ $lang['page']['Search'] ?? '검색' }}"
                    value="{{ $search_query ?? '' }}"
                >
                <button type="submit" class="pd-search-btn" aria-label="검색">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="15" height="15" fill="currentColor" aria-hidden="true">
                        <path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"/>
                    </svg>
                </button>
            </form>

            <nav class="pd-user" aria-label="사용자 메뉴">
                @if ($isLoggedIn)
                    <a href="/w/{{ rawurlencode('사용자:').rawurlencode((string) $username) }}" class="pd-user-name">
                        {{ $username }}
                    </a>
                    <a href="/member/mypage" class="pd-user-link">{{ $lang['page']['mypage'] ?? '내 정보' }}</a>
                    @foreach (($wiki['session']['menus'] ?? []) as $menu)
                        <a href="{{ $menu['l'] }}" class="pd-user-link">{{ $menu['label'] ?? $menu['t'] }}</a>
                    @endforeach
                    <a href="/member/logout" class="pd-user-link">{{ $lang['auth']['logout'] ?? '로그아웃' }}</a>
                @else
                    <a href="/member/login" class="pd-user-link">{{ $lang['page']['login'] ?? '로그인' }}</a>
                    <a href="/member/signup" class="pd-user-link pd-user-link-signup">{{ $lang['page']['signup'] ?? '회원가입' }}</a>
                @endif
            </nav>
        </div>
    </header>

    @if ($isDocPage)
        <nav class="pd-tabs" aria-label="문서 탭">
            <div class="pd-tabs-inner">
                <div class="pd-page-heading">
                    <h1 class="pd-page-title">{{ $pageDisplayTitle }}</h1>
                </div>
                <ul class="pd-tab-list">
                    <li>
                        <a href="/w/{{ $encodedTitle }}" class="pd-tab{{ $activeTab === 'wiki' ? ' pd-tab-active' : '' }}">
                            {{ $lang['nav']['wiki'] ?? '문서' }}
                        </a>
                    </li>
                    <li>
                        <a href="/discuss/{{ $encodedTitle }}" class="pd-tab{{ $activeTab === 'discuss' ? ' pd-tab-active' : '' }}">
                            {{ $lang['page']['discuss'] ?? '토론' }}
                            @if ($hasDiscussProgress)
                                <span class="pd-discuss-badge" aria-label="진행 중인 토론"></span>
                            @endif
                        </a>
                    </li>
                    <li>
                        <a href="/history/{{ $encodedTitle }}" class="pd-tab{{ $activeTab === 'history' ? ' pd-tab-active' : '' }}">
                            {{ $lang['page']['history'] ?? '역사' }}
                        </a>
                    </li>
                    <li>
                        <a href="/edit/{{ $encodedTitle }}" class="pd-tab{{ $activeTab === 'edit' ? ' pd-tab-active' : '' }}">
                            {{ $lang['page']['edit'] ?? '편집' }}
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    @endif

    <main class="pd-main">
        <div class="pd-content">
            {!! $innerLayout !!}
        </div>
    </main>

    <footer class="pd-footer">
        <div class="pd-footer-inner">
            <p>&copy; {{ $siteName }} &middot; Powered by
                <a href="https://github.com/PressDo/PressDoWiki" target="_blank" rel="noopener noreferrer">PressDoWiki</a>
            </p>
        </div>
    </footer>
</div>
