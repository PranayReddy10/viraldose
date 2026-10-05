<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\RssFeed;
use App\Models\User;
use App\Services\ArticleExtractor;
use App\Services\FeedImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArticleExtractorTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE = <<<'HTML'
<!doctype html><html><head><title>Site | Big Story</title>
<meta property="og:image" content="/img/lead-1200x800.jpg">
<meta property="og:title" content="Big Story"></head>
<body>
<header class="site-header"><nav><ul><li><a href="/">Home</a></li><li><a href="/sports">Sports</a></li><li><a href="/world">World</a></li></ul></nav></header>
<div class="breadcrumbs"><a href="/">Home</a> › <a href="/news">News</a></div>
<main>
<article>
  <h1>Big Story</h1>
  <div class="post-meta">By Reporter · 5 Oct 2026 · <a href="/author/r">Reporter</a></div>
  <div class="share-bar"><a href="https://facebook.com/share">Share on Facebook</a> <a href="https://x.com/share">Post on X</a></div>
  <div class="entry-content">
    <figure><a href="https://source.example/"><img data-src="/img/lead-1200x800.jpg" src="data:image/gif;base64,R0lGOD" alt="Lead"></a><figcaption>Lead photo</figcaption></figure>
    <p>The first paragraph of the article tells the reader what happened in the match and why it matters to the fans who stayed up late to watch it unfold on television.</p>
    <p>The second paragraph adds detail from the ground, quoting the captain at length about the decision to bowl first and how the pitch behaved under lights.</p>
    <div class="ad-slot"><script>ads()</script><a href="/ad">Buy now</a></div>
    <p>Also read: <a href="/other-story">Another story you may like</a></p>
    <p><a href="/images/inline.jpg"><img src="/images/inline.jpg" width="800" height="600" alt="Inline photo"></a></p>
    <p>A third paragraph continues the report with the scorecard and the player of the match award, before closing with the schedule for the next fixture.<img src="https://stats.wordpress.com/b.gif?x=1" width="1" height="1"></p>
    <ul class="tags"><li><a href="/tag/a">cricket</a></li><li><a href="/tag/b">final</a></li></ul>
  </div>
  <div class="related"><h3>Related stories</h3><ul><li><a href="/r1">Related one</a></li><li><a href="/r2">Related two</a></li></ul></div>
</article>
<aside class="sidebar"><div class="widget"><p>Trending now: lots of other headlines that are definitely not part of the article text but are long enough to look like one.</p></div></aside>
</main>
<footer><p>© Site. All rights reserved. Privacy policy. Terms of use. Contact us at the address below for more information about advertising.</p></footer>
<script type="application/ld+json">{"@type":"NewsArticle","articleBody":"Fallback body."}</script>
</body></html>
HTML;

    private const TEASER_RSS = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel><title>T</title>
<item><title>Big Story</title><link>https://source.example/news/big-story</link><guid>t-1</guid>
<description><![CDATA[<a href="https://source.example/"><img src="https://source.example/img/lead-1200x800.jpg"></a><p>Big Story teaser.</p>]]></description></item>
</channel></rss>
XML;

    public function test_extracts_article_body_and_strips_boilerplate(): void
    {
        $result = app(ArticleExtractor::class)->extract(self::PAGE, 'https://source.example/news/big-story');

        $this->assertNotNull($result);
        $html = $result['html'];
        $this->assertStringContainsString('The first paragraph of the article', $html);
        $this->assertStringContainsString('third paragraph continues', $html);
        $this->assertStringContainsString('src="https://source.example/images/inline.jpg"', $html, 'inline image kept with absolute URL');
        $this->assertStringContainsString('src="https://source.example/img/lead-1200x800.jpg"', $html, 'lazy data-src promoted');
        $this->assertStringNotContainsString('<a href="https://source.example/"><img', $html, 'image links unwrapped');
        $this->assertStringNotContainsString('<a href="https://source.example/images/inline.jpg">', $html);
        foreach (['Share on Facebook', 'Also read', 'Related one', 'Trending now', 'All rights reserved', 'Buy now', 'ads()', 'stats.wordpress', 'By Reporter', '>cricket<', 'Home'] as $noise) {
            $this->assertStringNotContainsString($noise, $html, "boilerplate leaked: {$noise}");
        }
        $this->assertSame('https://source.example/img/lead-1200x800.jpg', $result['image']);
        $this->assertSame('Big Story', $result['title']);
        $this->assertStringNotContainsString('class=', $html);
    }

    public function test_falls_back_to_json_ld_article_body(): void
    {
        $page = '<html><head><script type="application/ld+json">{"@graph":[{"@type":"NewsArticle","articleBody":"Para one is long enough to count as text for the test.\n\nPara two is also present and long enough."}]}</script></head><body><div>Teaser</div></body></html>';
        $result = app(ArticleExtractor::class)->extract($page, 'https://x.example/a');
        $this->assertSame('<p>Para one is long enough to count as text for the test.</p><p>Para two is also present and long enough.</p>', $result['html']);
    }

    public function test_tidy_removes_duplicate_lead_image_and_image_links(): void
    {
        $html = '<a href="https://src.example/"><img src="https://cdn.example/photos/lead-300x200.jpg"></a><p>Intro text that is long enough to count as a real sentence of article prose.</p><p><a href="/x"><img src="/photos/second.jpg"></a> caption</p>';
        $out = app(ArticleExtractor::class)->tidy($html, 'https://src.example/a/b', 'https://cdn.example/photos/lead.jpg');
        $this->assertStringNotContainsString('lead-300x200', $out);
        $this->assertStringNotContainsString('<a href="https://src.example/">', $out);
        $this->assertStringContainsString('<img src="https://src.example/photos/second.jpg"', $out);
        $this->assertStringNotContainsString('<a href="https://src.example/x">', $out);
    }

    public function test_feed_import_fetches_full_article_for_teaser_items(): void
    {
        Http::fake([
            'source.example/feed' => Http::response(self::TEASER_RSS, 200),
            'source.example/news/big-story' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html; charset=UTF-8']),
        ]);
        $feed = RssFeed::create(['name' => 'Source', 'url' => 'https://source.example/feed', 'category_id' => Category::factory()->create()->id, 'user_id' => User::factory()->create()->id]);

        $this->assertSame(1, app(FeedImporter::class)->import($feed));
        $post = Post::first();
        $this->assertSame('https://source.example/img/lead-1200x800.jpg', $post->image);
        $this->assertStringContainsString('The first paragraph of the article', $post->content);
        $this->assertStringContainsString('third paragraph continues', $post->content);
        $this->assertStringNotContainsString('lead-1200x800', $post->content, 'featured image is not repeated inside the article');
        $this->assertStringNotContainsString('href="https://source.example/"', $post->content, 'no clickable image pointing at the source home page');
        $this->assertStringContainsString('https://source.example/images/inline.jpg', $post->content);
        $this->assertNotNull($post->content_fetched_at);
        $this->assertSame('Big Story teaser.', $post->excerpt);
    }

    public function test_feed_import_keeps_teaser_when_full_fetch_is_off_or_fails(): void
    {
        Http::fake([
            'source.example/feed' => Http::response(self::TEASER_RSS, 200),
            'source.example/news/*' => Http::response('blocked', 403),
        ]);
        $category = Category::factory()->create();
        $user = User::factory()->create();

        $off = RssFeed::create(['name' => 'Off', 'url' => 'https://source.example/feed', 'category_id' => $category->id, 'user_id' => $user->id, 'fetch_full_content' => false]);
        app(FeedImporter::class)->import($off);
        $post = Post::first();
        $this->assertStringContainsString('Big Story teaser.', $post->content);
        $this->assertNull($post->content_fetched_at);
        $this->assertStringNotContainsString('<a href="https://source.example/">', $post->content, 'image link unwrapped even for teasers');
        $this->assertStringNotContainsString('<img', $post->content, 'lead image duplicate removed from teaser');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/news/'));

        Post::query()->forceDelete();
        $on = RssFeed::create(['name' => 'On', 'url' => 'https://source.example/feed', 'category_id' => $category->id, 'user_id' => $user->id]);
        app(FeedImporter::class)->import($on);
        $post = Post::first();
        $this->assertStringContainsString('Big Story teaser.', $post->content);
        $this->assertNotNull($post->content_fetched_at, 'attempt is recorded so the hourly refill does not loop on it');
        $this->assertSame(0, app(FeedImporter::class)->refill($on)['done']);
    }

    public function test_refill_and_pull_content_fill_existing_teaser_posts(): void
    {
        Http::fake(['source.example/news/*' => Http::response(self::PAGE, 200)]);
        $admin = $this->admin();
        $feed = RssFeed::create(['name' => 'Source', 'url' => 'https://source.example/feed', 'category_id' => Category::factory()->create()->id, 'user_id' => $admin->id]);
        $post = Post::factory()->create(['category_id' => $feed->category_id, 'user_id' => $admin->id, 'rss_feed_id' => $feed->id, 'source_url' => 'https://source.example/news/big-story', 'content' => '<p>Teaser only.</p>', 'image' => null]);

        $this->actingAs($admin)->post(route('admin.feeds.refill', $feed))->assertRedirect()->assertSessionHas('status');
        $post->refresh();
        $this->assertStringContainsString('The first paragraph of the article', $post->content);
        $this->assertSame('https://source.example/img/lead-1200x800.jpg', $post->image);

        // Per-post button pulls again on demand (even though content_fetched_at is set).
        $post->forceFill(['content' => '<p>Teaser only.</p>'])->save();
        $this->actingAs($admin)->get(route('admin.posts.edit', $post))->assertOk()->assertSee('Pull full article from source');
        $this->actingAs($admin)->post(route('admin.posts.pull-content', $post))->assertRedirect()->assertSessionHas('status');
        $this->assertStringContainsString('second paragraph adds detail', $post->fresh()->content);

        // Without a source URL the button is hidden and the action explains why.
        $other = Post::factory()->create(['category_id' => $feed->category_id, 'user_id' => $admin->id, 'source_url' => null]);
        $this->actingAs($admin)->get(route('admin.posts.edit', $other))->assertOk()->assertDontSee('Pull full article from source');
        $this->actingAs($admin)->post(route('admin.posts.pull-content', $other))->assertSessionHasErrors('source_url');
    }
}
