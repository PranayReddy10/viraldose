<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\Tag;
use App\Services\InstagramPublisher;
use App\Services\ShareCardGenerator;
use App\Support\WhatsAppShare;
use App\Support\XShare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    private function body(int $words = 450): string
    {
        return '<h2>Background</h2><p>'.str_repeat('Hyderabad metro ridership grew again this month. ', (int) ceil($words / 7)).'</p>'
            .'<p>Read <a href="https://othernews.example/story">this other site</a> and <a href="'.url('/india-news').'">our India news</a>.</p>';
    }

    private function token(): string
    {
        $this->admin();
        $response = $this->actingAs($this->admin())->post('/admin/agent/token');
        $response->assertRedirect();

        return session('agent_token');
    }

    public function test_api_requires_enabled_agent_and_valid_token(): void
    {
        $this->getJson('/api/agent/context')->assertStatus(403);

        $token = $this->token();
        $this->getJson('/api/agent/context', ['Authorization' => 'Bearer wrong'])->assertStatus(401);
        $this->getJson('/api/agent/context', ['Authorization' => "Bearer {$token}"])->assertOk()->assertJsonStructure(['categories', 'recent_posts', 'pending_drafts']);

        $this->actingAs($this->admin())->delete('/admin/agent/token');
        $this->getJson('/api/agent/context', ['Authorization' => "Bearer {$token}"])->assertStatus(403);
    }

    public function test_agent_creates_a_draft_without_external_links(): void
    {
        $token = $this->token();
        Category::factory()->create(['slug' => 'india-news', 'name' => 'India News']);

        $response = $this->postJson('/api/agent/posts', [
            'title' => 'Hyderabad Metro Ridership Crosses New Record This October',
            'category' => 'india-news',
            'content' => $this->body().'<script>alert(1)</script>',
            'tags' => ['Hyderabad Metro', 'Telangana'],
            'meta_title' => 'Hyderabad Metro Ridership Hits a New Record in October',
            'meta_description' => str_repeat('a', 155),
            'meta_keywords' => 'hyderabad metro, ridership',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertCreated()->assertJsonPath('status', 'draft');
        $post = Post::findOrFail($response->json('id'));
        $this->assertSame('agent', $post->created_via);
        $this->assertFalse($post->isPublished());
        $this->assertStringNotContainsString('othernews.example', $post->content);
        $this->assertStringContainsString('this other site', $post->content);
        $this->assertStringContainsString(url('/india-news'), $post->content);
        $this->assertStringNotContainsString('<script', $post->content);
        $this->assertCount(2, $post->tags);

        // Same headline again → conflict.
        $this->postJson('/api/agent/posts', [
            'title' => 'Hyderabad Metro Ridership Crosses New Record This October',
            'category' => 'india-news',
            'content' => $this->body(),
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(409);
    }

    public function test_agent_rejects_thin_content_and_unknown_category(): void
    {
        $token = $this->token();
        Category::factory()->create(['slug' => 'sports']);

        $this->postJson('/api/agent/posts', [
            'title' => 'A short article that should never be accepted',
            'category' => 'sports',
            'content' => '<p>Too short.</p>',
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(422)->assertJsonValidationErrors('content');

        $this->postJson('/api/agent/posts', [
            'title' => 'An article sent to a category that does not exist',
            'category' => 'nope',
            'content' => $this->body(),
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(422)->assertJsonValidationErrors('category');
    }

    public function test_agent_page_loads_for_admin(): void
    {
        $this->actingAs($this->admin())->get('/admin/agent')->assertOk()->assertSee('Agent API');
    }

    public function test_deleted_and_archived_posts_return_410(): void
    {
        $category = Category::factory()->create(['slug' => 'world']);
        $deleted = Post::factory()->create(['category_id' => $category->id, 'slug' => 'old-story']);
        $deleted->delete();
        $archived = Post::factory()->create(['category_id' => $category->id, 'slug' => 'archived-story', 'status' => Post::STATUS_ARCHIVED]);

        $this->get('/world/old-story')->assertStatus(410);
        $this->get('/old-story')->assertStatus(410);
        $this->get('/world/archived-story')->assertStatus(410);
        $this->get('/world/never-existed')->assertNotFound();

        // A redirect for a deleted URL still wins over 410.
        Redirect::create(['from_path' => '/world/old-story', 'to_path' => '/world', 'status_code' => 301]);
        $this->get('/world/old-story')->assertRedirect('/world');
    }

    public function test_google_indexing_api_ping_is_off_by_default(): void
    {
        $this->assertSame(0, (int) Setting::get('google_auto_index'));
    }

    public function test_agent_headline_cards_are_not_used_as_instagram_background(): void
    {
        $this->assertTrue(ShareCardGenerator::isTextCard('uploads/posts/2026/10/surya-launch-AbC123-card.jpg'));
        $this->assertFalse(ShareCardGenerator::isTextCard('uploads/posts/2026/10/ship-photo-abc123.jpg'));

        $legacy = new Post(['slug' => 'indias-first-indigenous-fleet-support-ship-surya', 'created_via' => 'agent']);
        $this->assertTrue(ShareCardGenerator::isTextCard('uploads/posts/2026/10/indias-first-indigenous-fleet-support-ship-surya-XyZ12a.jpg', $legacy));
        $this->assertFalse(ShareCardGenerator::isTextCard('uploads/posts/2026/10/navy-photo-abc123.jpg', $legacy));
        $this->assertFalse(ShareCardGenerator::isTextCard('uploads/posts/2026/10/indias-first-indigenous-fleet-support-ship-surya-XyZ12a-photo.jpg', $legacy));
    }

    public function test_share_card_renders_for_agent_post_without_photo_background(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create(['slug' => 'india', 'name' => 'India']);
        $post = Post::factory()->create([
            'category_id' => $category->id,
            'title' => "India's First Indigenous Fleet Support Ship Surya Launched in Vizag",
            'image' => 'uploads/posts/2026/10/surya-AbC123-card.jpg',
            'created_via' => 'agent',
        ]);

        $file = app(ShareCardGenerator::class)->generate($post->load('category'));
        $this->assertTrue(Storage::disk('public')->exists($file));
    }

    public function test_daily_plan_tracks_top_news_and_category_quotas(): void
    {
        $token = $this->token();
        $india = Category::factory()->create(['slug' => 'india', 'name' => 'India']);
        $cricket = Category::factory()->create(['slug' => 'cricket', 'name' => 'Cricket']);

        $this->actingAs($this->admin())->put('/admin/agent/plan', [
            'agent_top_news' => 2, 'agent_max_per_run' => 4, 'quotas' => [$india->id => 3, $cricket->id => 0],
        ])->assertRedirect();

        $plan = $this->getJson('/api/agent/context', ['Authorization' => "Bearer {$token}"])->assertOk()->json('plan');
        $this->assertSame(2, $plan['top_news']['remaining']);
        $this->assertSame(4, $plan['max_per_run']);
        $this->assertCount(1, $plan['categories']);
        $this->assertSame(5, $plan['remaining_total']);

        $res = $this->postJson('/api/agent/posts', [
            'title' => 'Top story of the day goes to the featured section now',
            'category' => 'cricket', 'content' => $this->body(), 'top' => true,
        ], ['Authorization' => "Bearer {$token}"])->assertCreated();
        $this->assertTrue(Post::find($res->json('id'))->is_featured);
        $this->assertSame(1, $res->json('plan.top_news.remaining'));

        $res = $this->postJson('/api/agent/posts', [
            'title' => 'An India category story that counts towards its quota',
            'category' => 'india', 'content' => $this->body(),
        ], ['Authorization' => "Bearer {$token}"])->assertCreated();
        $this->assertSame(2, $res->json('plan.categories.0.remaining'));
        $this->assertSame(3, $res->json('plan.remaining_total'));

        $this->actingAs($this->admin())->get('/admin/agent')->assertOk()->assertSee('Daily plan');
    }

    public function test_instagram_hashtags_come_from_post_tags_and_category(): void
    {
        Setting::set('instagram_hashtags', '#viraldose #news #india');
        $category = Category::factory()->create(['name' => 'India']);
        $post = Post::factory()->create(['category_id' => $category->id]);
        $post->tags()->sync(Tag::syncFromString('Indian Navy, Hindustan Shipyard, Vizag'));

        $tags = app(InstagramPublisher::class)->hashtags($post->fresh());
        $this->assertSame('#IndianNavy #HindustanShipyard #Vizag #India #viraldose #news', $tags);
    }

    public function test_agent_can_publish_directly_when_enabled(): void
    {
        $token = $this->token();
        Category::factory()->create(['slug' => 'india']);
        $this->actingAs($this->admin())->put('/admin/agent', ['agent_enabled' => 1, 'agent_auto_publish' => 1])->assertRedirect();

        $res = $this->postJson('/api/agent/posts', [
            'title' => 'This article goes live immediately without review',
            'category' => 'india', 'content' => $this->body(),
        ], ['Authorization' => "Bearer {$token}"])->assertCreated()->assertJsonPath('status', 'published');
        $this->assertTrue(Post::find($res->json('id'))->isPublished());
    }

    public function test_post_on_x_intent_link(): void
    {
        $category = Category::factory()->create(['name' => 'India', 'slug' => 'india']);
        $post = Post::factory()->create(['category_id' => $category->id, 'title' => 'Indian Navy Launches Fleet Support Ship Surya in Visakhapatnam']);
        $post->tags()->sync(Tag::syncFromString('Indian Navy, Visakhapatnam'));
        $post = $post->fresh();

        $text = XShare::text($post);
        $this->assertStringStartsWith($post->seoTitle()."\n\n".$post->url(), $text);
        $this->assertStringContainsString('#IndianNavy #Visakhapatnam', $text);
        $this->assertLessThanOrEqual(280, mb_strlen($text) - mb_strlen($post->url()) + 23);
        $this->assertStringStartsWith('https://x.com/intent/post?text=', XShare::url($post));

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/posts')->assertOk()->assertSee('x.com/intent/post', false);
        $this->actingAs($admin)->get("/admin/posts/{$post->id}/edit")->assertOk()->assertSee('Post on X');
    }

    public function test_whatsapp_channel_text_and_follow_banner(): void
    {
        $category = Category::factory()->create(['slug' => 'india']);
        $post = Post::factory()->create(['category_id' => $category->id, 'title' => 'Surya Launched in Vizag', 'excerpt' => 'The Navy got a new ship.']);

        $text = WhatsAppShare::text($post);
        $this->assertStringStartsWith("*Surya Launched in Vizag*\n\nThe Navy got a new ship.", $text);
        $this->assertStringEndsWith($post->url(), $text);

        $this->get($post->url())->assertOk()->assertDontSee('Follow on WhatsApp');
        Setting::set('whatsapp_url', 'https://whatsapp.com/channel/ABC123');
        $this->get($post->url())->assertOk()->assertSee('Follow on WhatsApp')->assertSee('whatsapp.com/channel/ABC123', false);

        $admin = $this->admin();
        $this->actingAs($admin)->get("/admin/posts/{$post->id}/edit")->assertOk()->assertSee('Copy for WhatsApp');
        $this->actingAs($admin)->get('/admin/posts')->assertOk()->assertSee('WA Copy');
    }
}
