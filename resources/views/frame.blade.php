@php
    $viewName = $wiki['page']['view_name'] ?? '';
    $pageData = $wiki['page']['data'] ?? [];
@endphp
<!DOCTYPE html>
<head>
    <link href="{{ $config['wiki.logo_url'] }}" rel="icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="user-scalable=no, initial-scale=1, width=device-width, viewport-fit=cover">
    <meta name="generator" content="PressDo">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="{{ $config['wiki.site_name'] }}">
    <meta name="msapplication-tooltip" content="{{ $config['wiki.site_name'] }}">
    <meta name="color-scheme" content="light dark">
    <meta name="msapplication-starturl" content="/w/{{ rawurlencode((string) $config['wiki.front_page']) }}">
    <link rel="canonical" href="{{ $config['wiki.canonical_url'].$request_uri }}">
    <link rel="search" type="application/opensearchdescription+xml" title="{{ $config['wiki.site_name'] }}" href="/opensearch.xml">
    <link rel="stylesheet" href="/src/style/document.css">
    <meta name="googlebot" content="noarchive">
    <meta name="robots" content="max-image-preview:large">
    <meta name="theme-color" content="">
    <meta name="monaco-version" content="{{ $config['wiki.editor_version'] }}">
    <script defer src="/src/script/main.js"></script>
    @if ($viewName === 'mypage' || !empty($pageData['use_webauthn']))
        <script defer src="/src/script/webauthn.js"></script>
    @endif
    @if (!empty($config['wiki.use_captcha']))
        <script src="{{ $api_config['captcha_api_endpoint'] }}" async defer></script>
    @endif
    <title>{{ $wiki['page']['title'] }} - {{ $config['wiki.site_name'] }}</title>
    @if ($viewName === 'wiki')
        <meta property="og:type" content="article">
        <meta property="og:title" content="{{ $wiki['page']['title'] }}">
        <meta property="og:site_name" content="{{ $config['wiki.site_name'] }}">
        <meta property="og:image" content="">
        <meta property="og:description" content="{{ $config['wiki.description'] }}">
        <meta property="og:url" content="{{ $config['wiki.canonical_url'].$request_uri }}">
        <script defer src="/src/script/document.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.11.1/dist/katex.min.css" integrity="sha384-zB1R0rpPzHqg7Kpt0Aljp8JPLqbXI3bhnPWROx27a9N0Ll6ZP/+DiW/UqRcLbRjq" crossorigin="anonymous">
        <script defer src="https://cdn.jsdelivr.net/npm/katex@0.11.1/dist/katex.min.js" integrity="sha384-y23I5Q6l+B6vatafAwxRu/0oK/79VlbSz7Q9aiSZUvyWYIYsd+qj+o24G5ZU2zJz" crossorigin="anonymous"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/katex@0.11.1/dist/contrib/auto-render.min.js" integrity="sha384-kWPLUVMOks5AQFrykwIup5lo0m3iMkkHrD0uJ4H5cjeGihAutqP0yW0J6dpFiVkI" crossorigin="anonymous" onload="renderMathInElement(document.body);"></script>
        <link rel="copyright" href="//creativecommons.org/licenses/by-nc-sa/2.0/kr/">
    @endif
    @if ($viewName === 'edit' || $viewName === 'edit_edit_request')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/{{ $config['wiki.editor_version'] }}/min/vs/loader.js"></script>
        <script src="https://cdn.jsdelivr.net/gh/PressDo/monaco-pressdo@latest/monaco.js"></script>
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
    <script>
        try {
            switch (JSON.parse(localStorage.getItem("pressdo_settings") || "{}")["wiki.theme"]) {
            case void 0:
            case "auto":
                var e,
                    a = matchMedia("(prefers-color-scheme: dark)");
                a && (e = a.matches ? 1 : 0);
                break;
            case "dark":
                e = 1;
                break;
            default:
                e = 0
            }
            var s = document.body.classList,
                t = "pressdo-dark-mode",
                d = "pressdo-light-mode";
            e ? (s.add(t), s.remove(d)) : (s.add(d), s.remove(t))
        } catch (r) {}
    </script>
    <div id="app">
        <div class="{{ implode(' ', $skinConfig['body_classes'] ?? []) }}">
            {!! $body !!}
        </div>
    </div>
</body>
