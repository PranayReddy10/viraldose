<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ url('/') }}</loc><changefreq>hourly</changefreq><priority>1.0</priority></url>
@foreach($categories as $category)
    <url>
        <loc>{{ $category->url() }}</loc>
        @if($lastPost->get($category->id))<lastmod>{{ \Illuminate\Support\Carbon::parse($lastPost->get($category->id))->toW3cString() }}</lastmod>@endif
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
@endforeach
</urlset>
