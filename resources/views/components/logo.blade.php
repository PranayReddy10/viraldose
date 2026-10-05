{{-- Site logo: custom upload from Settings → Branding wins, otherwise the built-in SVG wordmark. --}}
@props(['variant' => 'light', 'class' => 'h-9 w-auto', 'eager' => false])
@php
    $custom = $variant === 'dark' ? (media_url(setting('logo_dark')) ?: media_url(setting('logo'))) : media_url(setting('logo'));
    $src = $custom ?: asset($variant === 'dark' ? 'images/logo-white.svg' : 'images/logo.svg');
@endphp
<img src="{{ $src }}" alt="{{ site_name() }}" width="280" height="60" class="{{ $class }}" @if($eager) fetchpriority="high" decoding="sync" @else loading="lazy" decoding="async" @endif>
