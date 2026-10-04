<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($pages as $page)
    <url><loc>{{ $page->url() }}</loc><lastmod>{{ $page->updated_at->toW3cString() }}</lastmod><changefreq>monthly</changefreq><priority>0.3</priority></url>
@endforeach
    <url><loc>{{ route('contact') }}</loc><changefreq>yearly</changefreq><priority>0.3</priority></url>
</urlset>
