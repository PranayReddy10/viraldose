@php
    /** @var \App\Services\Seo $seo */
    $seo = app(\App\Services\Seo::class);
    $siteName = site_name();
    $logo = media_url(setting('logo'));
    $favicon = media_url(setting('favicon'));
    $ogImage = $seo->resolvedImage();
    $canonical = $seo->resolvedCanonical();
@endphp
<!DOCTYPE html>
<html lang="{{ setting('language', 'en') }}" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $seo->fullTitle() }}</title>
    <meta name="description" content="{{ $seo->resolvedDescription() }}">
    <meta name="robots" content="{{ $seo->robots }}">
    <link rel="canonical" href="{{ $canonical }}">
    @if($seo->prev)<link rel="prev" href="{{ $seo->prev }}">@endif
    @if($seo->next)<link rel="next" href="{{ $seo->next }}">@endif
    <meta name="theme-color" content="#dc2626">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(setting('google_site_verification'))<meta name="google-site-verification" content="{{ setting('google_site_verification') }}">@endif
    @if(setting('bing_site_verification'))<meta name="msvalidate.01" content="{{ setting('bing_site_verification') }}">@endif

    {{-- Open Graph --}}
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="{{ $seo->type }}">
    <meta property="og:title" content="{{ $seo->fullTitle() }}">
    <meta property="og:description" content="{{ $seo->resolvedDescription() }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="{{ str_replace('-', '_', setting('language', 'en')) === 'en' ? 'en_IN' : str_replace('-', '_', setting('language')) }}">
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
        @if($seo->imageWidth)<meta property="og:image:width" content="{{ $seo->imageWidth }}">@endif
        @if($seo->imageHeight)<meta property="og:image:height" content="{{ $seo->imageHeight }}">@endif
    @endif
    @if($seo->type === 'article')
        @if($seo->publishedTime)<meta property="article:published_time" content="{{ $seo->publishedTime }}">@endif
        @if($seo->modifiedTime)<meta property="article:modified_time" content="{{ $seo->modifiedTime }}">@endif
        @if($seo->section)<meta property="article:section" content="{{ $seo->section }}">@endif
        @foreach($seo->tags as $tag)<meta property="article:tag" content="{{ $tag }}">@endforeach
    @endif

    {{-- Twitter --}}
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    @if(setting('twitter_handle'))<meta name="twitter:site" content="{{ '@'.ltrim(setting('twitter_handle'), '@') }}">@endif
    <meta name="twitter:title" content="{{ $seo->fullTitle() }}">
    <meta name="twitter:description" content="{{ $seo->resolvedDescription() }}">
    @if($ogImage)<meta name="twitter:image" content="{{ $ogImage }}">@endif

    {{-- Feeds & icons --}}
    <link rel="alternate" type="application/rss+xml" title="{{ $siteName }} RSS" href="{{ $seo->feed ?: route('feed') }}">
    @if($favicon)
        <link rel="icon" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @else
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @foreach($seo->jsonLd as $ld)
        <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endforeach

    @if(setting('google_analytics_id') && app()->isProduction())
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ setting('google_analytics_id') }}"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ setting('google_analytics_id') }}');</script>
    @endif
    @if(setting('adsense_client_id') && app()->isProduction())
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ str_starts_with(setting('adsense_client_id'), 'ca-') ? setting('adsense_client_id') : 'ca-'.setting('adsense_client_id') }}" crossorigin="anonymous"></script>
    @endif
    {!! setting('head_scripts') !!}
    @stack('head')
</head>
<body class="min-h-screen flex flex-col">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-2">Skip to content</a>
@hasSection('progress')<div id="progress-bar" class="fixed top-0 left-0 z-50 h-1 bg-brand-600" style="width:0"></div>@endif

@include('partials.header')

<main id="main" class="flex-1">
    @if(session('status'))
        <div class="container-site mt-4"><div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 border border-green-200">{{ session('status') }}</div></div>
    @endif
    @yield('content')
</main>

@include('partials.footer')

<button id="back-to-top" type="button" aria-label="Back to top" class="hidden fixed bottom-5 right-5 z-40 rounded-full bg-ink-900/80 p-3 text-white shadow-lg hover:bg-brand-600">
    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
</button>

{!! setting('body_scripts') !!}
@stack('scripts')
</body>
</html>
