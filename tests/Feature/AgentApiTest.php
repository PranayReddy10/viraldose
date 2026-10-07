<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Setting;
use App\Services\ShareCardGenerator;
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
}
