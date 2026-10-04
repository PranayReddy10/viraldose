<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
@foreach($posts as $post)
    <url>
        <loc>{{ $post->url() }}</loc>
        <news:news>
            <news:publication>
                <news:name>{{ setting('google_news_publication_name', site_name()) }}</news:name>
                <news:language>{{ setting('language', 'en') }}</news:language>
            </news:publication>
            <news:publication_date>{{ $post->published_at->toW3cString() }}</news:publication_date>
            <news:title>{{ $post->title }}</news:title>
            @if($post->tags->isNotEmpty())<news:keywords>{{ $post->tags->pluck('name')->implode(', ') }}</news:keywords>@endif
        </news:news>
    </url>
@endforeach
</urlset>
