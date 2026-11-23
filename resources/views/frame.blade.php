@php
    $viewName = $wiki['page']['view_name'] ?? '';
    $pageData = $wiki['page']['data'] ?? [];
    $siteName = $config['wiki.site_name'] ?? 'PressDoWiki';
    $canonicalUrl = ($config['wiki.canonical_url'] ?? '') . ($request_uri ?? '');
@endphp
<!DOCTYPE html>
<head>
    <link href="{{ $config['wiki.logo_url'] ?? '/favicon.ico' }}" rel="icon">
    <meta charset="UTF-8">
    @if (isset($csrfToken))
        <meta name="csrf-token" content="{{ $csrfToken }}">
    @endif
    <meta name="viewport" content="user-scalable=no, initial-scale=1, width=device-width, viewport-fit=cover">
    <meta name="generator" content="PressDo">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="{{ $siteName }}">
    <meta name="msapplication-tooltip" content="{{ $siteName }}">
    <meta name="color-scheme" content="light dark">
    <meta name="msapplication-starturl" content="/w/{{ rawurlencode((string) $config['wiki.front_page']) }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="search" type="application/opensearchdescription+xml" title="{{ $siteName }}" href="/opensearch.xml">
    <link rel="stylesheet" href="/src/style/document.css">
    <meta name="googlebot" content="noarchive">
    <meta name="robots" content="max-image-preview:large">
    <meta name="theme-color" content="">
    <script defer src="/src/script/main.js"></script>
    @if ($viewName === 'mypage' || !empty($pageData['use_webauthn']))
        <script defer src="/src/script/webauthn.js"></script>
    @endif
    @if (!empty($config['wiki.use_captcha']))
        <script src="{{ $api_config['captcha_api_endpoint'] }}" async defer></script>
    @endif
    <title>{{ $wiki['page']['title'] }} - {{ $siteName }}</title>
    @if ($viewName === 'wiki')
        <meta property="og:type" content="article">
        <meta property="og:title" content="{{ $wiki['page']['title'] }}">
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:image" content="">
        <meta property="og:description" content="{{ $config['wiki.description'] ?? '' }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <script defer src="/src/script/document.js"></script>
        <link rel="copyright" href="//creativecommons.org/licenses/by-nc-sa/2.0/kr/">
    @endif
    @if (in_array($viewName, ['wiki', 'edit', 'edit_edit_request'], true))
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.18.1/dist/katex.min.css" integrity="sha384-1vdNCNel6Tx/NQa8IR1mGOGKsbGreCkOPfbtPPnUURJ5Tu2PRVfQ/7KLZC+Pi1p1" crossorigin="anonymous">
        <script defer src="https://cdn.jsdelivr.net/npm/katex@0.18.1/dist/katex.min.js" integrity="sha384-ycJ6GAwiS15LoUPipwJOrWTvkUHl/YqELValBwI5I4awP1EeEQJYarj+w85ntcz7" crossorigin="anonymous"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/katex@0.18.1/dist/contrib/auto-render.min.js" integrity="sha384-bjyGPfbij8/NDKJhSGZNP/khQVgtHUE5exjm4Ydllo42FwIgYsdLO2lXGmRBf5Mz" crossorigin="anonymous"></script>
        <script defer src="/src/script/math.js"></script>
    @endif
    @if ($viewName === 'edit' || $viewName === 'edit_edit_request')
        <script type="module" src="/src/script/editor.js"></script>
    @endif
    @foreach (($skinConfig['js'] ?? []) as $skinScript)
        <link rel="preload" as="script" href="/skins/{{ $skinName }}/{{ $skinScript['path'] }}">
        <script defer src="/skins/{{ $skinName }}/{{ $skinScript['path'] }}"></script>
    @endforeach
    {!! implode('', $skinConfig['additional_heads'] ?? []) !!}
    <style>
        @import "/src/style/bootstrap.min.css" layer(skin);
    </style>
    <link rel="stylesheet" href="/src/style/base.css">
    <link rel="stylesheet" href="/src/style/skin.css">
</head>
<body>
    <script src="/src/script/theme.js"></script>
    <div id="app">
        <div class="{{ implode(' ', $skinConfig['body_classes'] ?? []) }}">
            {!! $body !!}
        </div>
    </div>
</body>
