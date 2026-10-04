<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">
    <url><loc>{{ route('reels.index') }}</loc><changefreq>daily</changefreq><priority>0.7</priority></url>
@foreach($reels as $reel)
    <url>
        <loc>{{ $reel->url() }}</loc>
        <lastmod>{{ $reel->updated_at->toW3cString() }}</lastmod>
        @if($reel->thumbnailUrl() && ($reel->videoUrl() || $reel->embedUrl()))
        <video:video>
            <video:thumbnail_loc>{{ $reel->thumbnailUrl() }}</video:thumbnail_loc>
            <video:title>{{ $reel->title }}</video:title>
            <video:description>{{ $reel->caption ?: $reel->title }}</video:description>
            @if($reel->videoUrl())<video:content_loc>{{ $reel->videoUrl() }}</video:content_loc>@else<video:player_loc>{{ $reel->embedUrl() }}</video:player_loc>@endif
            <video:publication_date>{{ $reel->published_at->toW3cString() }}</video:publication_date>
        </video:video>
        @endif
    </url>
@endforeach
</urlset>
