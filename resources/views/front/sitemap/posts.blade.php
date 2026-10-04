<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach($posts as $post)
    <url>
        <loc>{{ $post->url() }}</loc>
        <lastmod>{{ $post->updated_at->toW3cString() }}</lastmod>
        @if($post->image)
        <image:image>
            <image:loc>{{ $post->imageUrl('large') }}</image:loc>
            <image:title>{{ $post->title }}</image:title>
        </image:image>
        @endif
    </url>
@endforeach
</urlset>
