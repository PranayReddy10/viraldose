<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($authors as $author)
    <url><loc>{{ $author->url() }}</loc><changefreq>weekly</changefreq><priority>0.4</priority></url>
@endforeach
</urlset>
