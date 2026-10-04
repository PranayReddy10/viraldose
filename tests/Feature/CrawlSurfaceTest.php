<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Redirect;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_points_to_sitemaps(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap: http://localhost/sitemap.xml')
            ->assertSee('Disallow: /admin');
    }

    public function test_sitemap_index_and_children(): void
    {
        $post = $this->publishedPost();
        Post::factory()->draft()->create(['category_id' => $post->category_id, 'slug' => 'hidden-draft']);
        Post::factory()->create(['category_id' => $post->category_id, 'slug' => 'hidden-noindex', 'noindex' => true]);

        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<sitemapindex', false)
            ->assertSee('sitemap-posts-1.xml')
            ->assertSee('sitemap-categories.xml');

        $this->get('/sitemap-posts-1.xml')->assertOk()
            ->assertSee('http://localhost/sports/india-wins-the-final')
            ->assertDontSee('hidden-draft')
            ->assertDontSee('hidden-noindex');

        $this->get('/sitemap-categories.xml')->assertOk()->assertSee('http://localhost/category/sports');
        $this->get('/sitemap-pages.xml')->assertOk();
        $this->get('/sitemap-tags.xml')->assertOk();
        $this->get('/sitemap-authors.xml')->assertOk()->assertSee('/author/');
        $this->get('/sitemap-posts-5.xml')->assertNotFound();
    }

    public function test_news_sitemap_only_includes_last_48_hours(): void
    {
        $post = $this->publishedPost();
        Post::factory()->create(['category_id' => $post->category_id, 'slug' => 'old-story', 'published_at' => now()->subDays(5)]);

        $this->get('/news-sitemap.xml')->assertOk()
            ->assertSee('<news:news>', false)
            ->assertSee('india-wins-the-final')
            ->assertDontSee('old-story');
    }

    public function test_rss_feed(): void
    {
        $this->publishedPost();

        $this->get('/feed')->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->assertSee('<rss', false)
            ->assertSee('India wins the final');
        $this->get('/feed/category/sports')->assertOk()->assertSee('India wins the final');
        $this->get('/rss')->assertRedirect('/feed');
    }

    public function test_redirect_table_handles_legacy_urls_before_404(): void
    {
        Redirect::create(['from_path' => '/2023/old-post.html', 'to_path' => '/sports/india-wins-the-final', 'status_code' => 301]);

        $this->get('/2023/Old-Post.html')->assertStatus(301)->assertRedirect('/sports/india-wins-the-final');
        $this->assertSame(1, Redirect::first()->hits);
    }

    public function test_ads_txt_served_only_when_configured(): void
    {
        $this->get('/ads.txt')->assertNotFound();
        Setting::set('ads_txt', 'google.com, pub-123, DIRECT, f08c47fec0942fa0');
        $this->get('/ads.txt')->assertOk()->assertSee('pub-123');
    }
}
