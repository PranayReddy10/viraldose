<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:media="http://search.yahoo.com/mrss/">
<channel>
    <title>{{ $title }}</title>
    <link>{{ $link }}</link>
    <description>{{ seo_truncate($description, 300) }}</description>
    <language>{{ setting('language', 'en') }}</language>
    <lastBuildDate>{{ $lastBuild->toRssString() }}</lastBuildDate>
    <atom:link href="{{ $self }}" rel="self" type="application/rss+xml"/>
    @foreach($posts as $post)
    <item>
        <title>{{ $post->title }}</title>
        <link>{{ $post->url() }}</link>
        <guid isPermaLink="true">{{ $post->url() }}</guid>
        <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
        <dc:creator>{{ $post->author?->name }}</dc:creator>
        <category>{{ $post->category?->name }}</category>
        <description>{{ $post->excerpt }}</description>
        @if($post->imageUrl('medium'))<media:content url="{{ $post->imageUrl('medium') }}" medium="image"/>@endif
    </item>
    @endforeach
</channel>
</rss>
