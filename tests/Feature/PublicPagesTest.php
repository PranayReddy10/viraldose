<?php

namespace Tests\Feature;

use App\Http\Middleware\NormalizeUrl;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_with_seo_tags(): void
    {
        $this->publishedPost();

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost">', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('India wins the final')
            ->assertSee('<meta name="robots" content="index, follow', false);
    }

    public function test_home_page_works_on_fresh_install_without_posts(): void
    {
        $this->get('/')->assertOk()->assertSee('No articles published yet');
    }

    public function test_post_page_has_news_article_schema_and_canonical(): void
    {
        $post = $this->publishedPost();

        $response = $this->get('/sports/india-wins-the-final');

        $response->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost/sports/india-wins-the-final">', false)
            ->assertSee('"@type":"NewsArticle"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('<h1', false)
            ->assertHeader('Last-Modified');

        $this->assertSame(1, $post->fresh()->views);
    }

    public function test_flat_legacy_url_redirects_to_canonical_post_url(): void
    {
        $this->publishedPost();

        $this->get('/india-wins-the-final')->assertRedirect('http://localhost/sports/india-wins-the-final');
        $this->get('/wrong-category/india-wins-the-final')->assertRedirect('http://localhost/sports/india-wins-the-final');
    }

    public function test_uppercase_urls_are_normalised_with_301(): void
    {
        $this->publishedPost();

        $this->get('/Sports/India-Wins-The-Final')->assertStatus(301)->assertRedirect('/sports/india-wins-the-final');
    }

    public function test_trailing_slash_urls_are_normalised_with_301(): void
    {
        // The HTTP test client trims trailing slashes, so exercise the middleware directly.
        $middleware = new NormalizeUrl;
        $request = Request::create('http://localhost/sports/india-wins-the-final/?utm_source=x', 'GET');

        $response = $middleware->handle($request, fn () => response('passed'));

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('http://localhost/sports/india-wins-the-final?utm_source=x', $response->headers->get('Location'));

        $root = $middleware->handle(Request::create('http://localhost/', 'GET'), fn () => response('passed'));
        $this->assertSame('passed', $root->getContent());
    }

    public function test_draft_and_scheduled_posts_are_not_public(): void
    {
        $category = Category::factory()->create(['slug' => 'news']);
        Post::factory()->draft()->create(['category_id' => $category->id, 'slug' => 'secret-draft']);
        Post::factory()->scheduled()->create(['category_id' => $category->id, 'slug' => 'tomorrow']);

        $this->get('/news/secret-draft')->assertNotFound();
        $this->get('/news/tomorrow')->assertNotFound();
    }

    public function test_staff_can_preview_drafts(): void
    {
        $category = Category::factory()->create(['slug' => 'news']);
        Post::factory()->draft()->create(['category_id' => $category->id, 'slug' => 'secret-draft']);

        $this->actingAs($this->admin())->get('/news/secret-draft?preview=1')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_category_page_lists_posts_and_paginates_with_canonical(): void
    {
        $post = $this->publishedPost();
        Post::factory()->count(15)->create(['category_id' => $post->category_id]);

        $this->get('/category/sports')->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost/category/sports">', false)
            ->assertSee('rel="next"', false);

        $this->get('/category/sports?page=2')->assertOk()
            ->assertSee('<link rel="canonical" href="http://localhost/category/sports?page=2">', false);

        $this->get('/category/sports?page=99')->assertNotFound();
    }

    public function test_sub_category_posts_show_under_parent_category(): void
    {
        $parent = Category::factory()->create(['slug' => 'sports']);
        $child = Category::factory()->create(['slug' => 'cricket', 'parent_id' => $parent->id]);
        $child2 = Category::factory()->create(['slug' => 'football', 'parent_id' => $parent->id]);
        $grandchild = Category::factory()->create(['slug' => 'ipl', 'parent_id' => $child->id]);
        Category::factory()->count(3)->create(); // extra top-level categories so strict lazy-loading checks engage
        Post::factory()->create(['category_id' => $child->id, 'title' => 'Cricket story in child']);
        Post::factory()->create(['category_id' => $child2->id, 'title' => 'Football story in child']);
        Post::factory()->create(['category_id' => $grandchild->id, 'title' => 'IPL story in grandchild']);

        $this->assertEqualsCanonicalizing([$parent->id, $child->id, $child2->id, $grandchild->id], $parent->fresh()->treeIds());
        $this->get('/category/sports')->assertOk()
            ->assertSee('Cricket story in child')->assertSee('Football story in child')->assertSee('IPL story in grandchild');
        // Archive cards use <h2>; the sidebar may still list the football story under Trending (<h3>).
        $this->get('/category/cricket')->assertOk()->assertSee('IPL story in grandchild')
            ->assertDontSee('<h2 class="mt-1 text-lg font-bold leading-snug line-clamp-2"><a href="http://localhost/football/football-story-in-child"', false);
        $this->get('/')->assertOk()->assertSee('IPL story in grandchild');
    }

    public function test_tag_author_and_search_pages(): void
    {
        $post = $this->publishedPost();
        $tag = Tag::factory()->create(['name' => 'Cricket', 'slug' => 'cricket']);
        $post->tags()->attach($tag);

        $this->get('/tag/cricket')->assertOk()->assertSee('India wins the final')
            ->assertSee('<meta name="robots" content="noindex, follow">', false); // thin tag archive
        $this->get('/author/'.$post->author->slug)->assertOk()->assertSee('"@type":"ProfilePage"', false);
        $this->get('/search?q=india')->assertOk()->assertSee('India wins the final')
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get('/search?q=zzzz-nothing')->assertOk()->assertSee('No articles found');
    }

    public function test_static_page_and_flat_page_url(): void
    {
        Page::factory()->create(['title' => 'About Us', 'slug' => 'about-us']);

        $this->get('/page/about-us')->assertOk()->assertSee('About Us');
        $this->get('/about-us')->assertOk()->assertSee('About Us');
    }

    public function test_404_page_is_branded_and_noindex(): void
    {
        $this->get('/this-does-not-exist')->assertNotFound()
            ->assertSee('404')
            ->assertSee('noindex', false);
    }

    public function test_contact_form_stores_message_and_blocks_honeypot(): void
    {
        $this->post('/contact', ['name' => 'Ram', 'email' => 'ram@example.com', 'message' => 'Hello there, nice site!'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseCount('contact_messages', 1);

        $this->post('/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'spam spam spam spam', 'website' => 'x'])
            ->assertSessionHasErrors('website');
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_newsletter_subscription(): void
    {
        $this->post('/newsletter', ['email' => 'Reader@Example.com'])->assertRedirect()->assertSessionHas('newsletter_status');
        $this->assertDatabaseHas('subscribers', ['email' => 'reader@example.com']);
    }

    public function test_comment_goes_to_moderation_queue(): void
    {
        $post = $this->publishedPost();

        $this->post("/posts/{$post->id}/comments", ['name' => 'Reader', 'email' => 'r@example.com', 'body' => 'Great article!'])
            ->assertRedirect();
        $this->assertDatabaseHas('comments', ['post_id' => $post->id, 'status' => 'pending']);
        $this->get($post->url())->assertDontSee('Great article!');
    }

    public function test_article_sidebar_shows_popular_in_category_and_latest_news(): void
    {
        $india = Category::factory()->create(['name' => 'India', 'slug' => 'india']);
        $world = Category::factory()->create(['name' => 'World', 'slug' => 'world']);
        $post = Post::factory()->create(['category_id' => $india->id, 'title' => 'The article being read']);
        Post::factory()->create(['category_id' => $india->id, 'title' => 'Another India story here', 'published_at' => now()->subDays(40)]);
        Post::factory()->create(['category_id' => $world->id, 'title' => 'Fresh world headline today', 'published_at' => now()->subMinutes(5)]);

        $this->get($post->url())->assertOk()
            ->assertSee('Popular in India')->assertSee('Another India story here')
            ->assertSee('Latest News')->assertSee('Fresh world headline today');
    }
}
