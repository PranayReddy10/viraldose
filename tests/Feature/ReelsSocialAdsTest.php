<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\Post;
use App\Models\Reel;
use App\Models\Setting;
use App\Models\SocialShare;
use App\Services\InstagramPublisher;
use App\Services\ShareCardGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReelsSocialAdsTest extends TestCase
{
    use RefreshDatabase;

    private function connectInstagram(): void
    {
        Setting::setMany(['instagram_business_id' => '17841400000000000', 'instagram_access_token' => Crypt::encryptString('TOKEN')]);
    }

    // ---- Reels -----------------------------------------------------------

    public function test_reels_feed_page_single_reel_page_and_fragment(): void
    {
        $category = Category::factory()->create(['slug' => 'sports']);
        $post = $this->publishedPost();
        Reel::factory()->count(12)->create(['category_id' => $category->id]);
        $first = Reel::factory()->create(['title' => 'Upload reel', 'source_type' => 'url', 'external_url' => null, 'video_path' => 'https://cdn.example.com/clip.mp4', 'post_id' => $post->id, 'sort_order' => 0, 'published_at' => now()]);
        Reel::factory()->create(['title' => 'Hidden reel', 'is_active' => false]);

        $this->get('/reels')->assertOk()
            ->assertSee('Upload reel')->assertSee('cdn.example.com/clip.mp4')->assertSee('Read the full story')
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertDontSee('Hidden reel')
            ->assertSee('data-next="http://localhost/reels?page=2&amp;fragment=1"', false);

        $this->get('/reels?page=2&fragment=1')->assertOk()->assertSee('reel-slide')->assertDontSee('<html', false);

        $this->get($first->url())->assertOk()
            ->assertSee('"@type":"VideoObject"', false)
            ->assertSee('"contentUrl":"https://cdn.example.com/clip.mp4"', false)
            ->assertSee('<link rel="canonical" href="'.$first->url().'">', false);
        $this->assertSame(1, $first->fresh()->views - $first->views);

        $this->get('/')->assertOk()->assertSee('See all')->assertSee($first->url());
        $this->get('/sitemap.xml')->assertSee('sitemap-reels.xml');
        $this->get('/sitemap-reels.xml')->assertOk()->assertSee($first->url())->assertSee('<video:video>', false);
    }

    public function test_reels_can_be_disabled(): void
    {
        Setting::set('reels_enabled', 0);
        Reel::factory()->create();
        $this->get('/reels')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('/reels');
    }

    public function test_admin_can_create_reels_from_each_source(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/reels')->assertOk();
        $this->actingAs($admin)->get('/admin/reels/create')->assertOk()->assertSee('YouTube Shorts');

        $this->actingAs($admin)->post('/admin/reels', ['title' => 'Short one', 'source_type' => 'youtube', 'external_url' => 'https://youtube.com/shorts/dQw4w9WgXcQ?feature=share', 'is_active' => 1])->assertRedirect('/admin/reels');
        $yt = Reel::where('title', 'Short one')->first();
        $this->assertSame('dQw4w9WgXcQ', $yt->external_id);
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $yt->thumbnail);

        $this->actingAs($admin)->post('/admin/reels', ['title' => 'Insta one', 'source_type' => 'instagram', 'external_url' => 'https://www.instagram.com/reel/Cabc123xyz/', 'is_active' => 1])->assertRedirect();
        $this->assertSame('Cabc123xyz', Reel::where('title', 'Insta one')->value('external_id'));
        $this->assertStringContainsString('instagram.com/reel/Cabc123xyz/embed', Reel::where('title', 'Insta one')->first()->embedUrl());

        $this->actingAs($admin)->post('/admin/reels', ['title' => 'Bad', 'source_type' => 'youtube', 'external_url' => 'https://example.com/x'])->assertSessionHasErrors('external_url');

        $this->actingAs($admin)->post('/admin/reels', ['title' => 'Uploaded', 'source_type' => 'upload', 'video' => UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'), 'thumbnail' => UploadedFile::fake()->image('t.jpg', 720, 1280), 'is_active' => 1])->assertRedirect();
        $up = Reel::where('title', 'Uploaded')->first();
        Storage::disk('public')->assertExists($up->video_path);
        $this->assertStringContainsString('/storage/uploads/reels/', $up->videoUrl());
        $this->assertStringEndsWith('.webp', $up->thumbnailUrl());

        $this->actingAs($admin)->post('/admin/reels', ['title' => 'Direct', 'source_type' => 'url', 'video_url' => 'https://cdn.example.com/a.mp4', 'is_active' => 1])->assertRedirect();
        $this->assertSame('https://cdn.example.com/a.mp4', Reel::where('title', 'Direct')->first()->videoUrl());

        $this->actingAs($admin)->get(route('admin.reels.edit', $up))->assertOk();
        $this->actingAs($admin)->delete(route('admin.reels.destroy', $up))->assertRedirect();
        Storage::disk('public')->assertMissing($up->video_path);
    }

    public function test_create_reel_prefilled_from_video_post(): void
    {
        $post = $this->publishedPost(['post_type' => 'video', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ']);
        $this->actingAs($this->admin())->get('/admin/reels/create?post='.$post->id)->assertOk()
            ->assertSee('value="https://youtu.be/dQw4w9WgXcQ"', false)->assertSee($post->title);
    }

    // ---- Instagram -------------------------------------------------------

    public function test_share_card_is_generated_as_portrait_jpeg(): void
    {
        Storage::fake('public');
        $post = $this->publishedPost(['image' => null]);
        $file = app(ShareCardGenerator::class)->generate($post->load('category'));
        Storage::disk('public')->assertExists($file);
        [$w, $h, $type] = getimagesizefromstring(Storage::disk('public')->get($file));
        $this->assertSame([1080, 1350, IMAGETYPE_JPEG], [$w, $h, $type]);

        $this->actingAs($this->admin())->get(route('admin.posts.share.card', $post))->assertOk()->assertDownload('instagram-'.$post->slug.'.jpg');
    }

    public function test_one_click_instagram_post_publishes_card_and_records_share(): void
    {
        Storage::fake('public');
        $this->connectInstagram();
        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'CONTAINER1']),
            'graph.facebook.com/*/CONTAINER1?*' => Http::response(['status_code' => 'FINISHED']),
            'graph.facebook.com/*/media_publish' => Http::response(['id' => 'MEDIA9']),
            'graph.facebook.com/*/MEDIA9?*' => Http::response(['permalink' => 'https://www.instagram.com/p/abc/']),
        ]);
        $post = $this->publishedPost();

        $this->actingAs($this->admin())->post(route('admin.posts.share.instagram', $post), ['caption' => 'Custom caption #news'])
            ->assertRedirect()->assertSessionHas('status');

        $share = SocialShare::first();
        $this->assertSame('published', $share->status);
        $this->assertSame('MEDIA9', $share->external_id);
        $this->assertSame('https://www.instagram.com/p/abc/', $share->permalink);
        $this->assertStringStartsWith('uploads/social/post-', $share->image);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/media') && $r['caption'] === 'Custom caption #news' && str_contains($r['image_url'], '/storage/uploads/social/'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/media_publish') && $r['creation_id'] === 'CONTAINER1');

        $this->actingAs($this->admin())->get(route('admin.posts.edit', $post))->assertOk()->assertSee('Post card to Instagram')->assertSee('instagram.com/p/abc');
    }

    public function test_instagram_failure_is_recorded_and_reported(): void
    {
        Storage::fake('public');
        $this->connectInstagram();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400)]);
        $post = $this->publishedPost();

        $this->actingAs($this->admin())->post(route('admin.posts.share.instagram', $post))->assertSessionHasErrors('instagram');
        $this->assertSame('failed', SocialShare::first()->status);
        $this->assertStringContainsString('Invalid OAuth', SocialShare::first()->response);
    }

    public function test_reel_share_waits_for_processing_then_scheduler_publishes(): void
    {
        $this->connectInstagram();
        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'VID1']),
            'graph.facebook.com/*/VID1?*' => Http::sequence()->push(['status_code' => 'IN_PROGRESS'])->push(['status_code' => 'FINISHED']),
            'graph.facebook.com/*/media_publish' => Http::response(['id' => 'MED2']),
            'graph.facebook.com/*/MED2?*' => Http::response(['permalink' => 'https://www.instagram.com/reel/xyz/']),
        ]);
        $post = $this->publishedPost(['post_type' => 'video', 'video_url' => 'https://cdn.example.com/clip.mp4']);

        $this->actingAs($this->admin())->post(route('admin.posts.share.instagram', $post), ['media' => 'reel'])->assertSessionHas('status');
        $this->assertSame('processing', SocialShare::first()->status);

        $this->artisan('social:process')->assertSuccessful();
        $this->assertSame('processing', SocialShare::first()->status);
        $this->artisan('social:process')->assertSuccessful();
        $this->assertSame('published', SocialShare::first()->status);
        $this->assertSame('https://www.instagram.com/reel/xyz/', SocialShare::first()->permalink);
    }

    public function test_auto_share_on_publish_and_caption_template(): void
    {
        Storage::fake('public');
        $this->connectInstagram();
        Setting::setMany(['instagram_auto_share' => 1, 'instagram_caption_template' => '{title} | {category} {hashtags}', 'instagram_hashtags' => '#vd']);
        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'CONT3']),
            'graph.facebook.com/*/CONT3?*' => Http::response(['status_code' => 'FINISHED']),
            'graph.facebook.com/*/media_publish' => Http::response(['id' => 'MED3']),
            'graph.facebook.com/*/MED3?*' => Http::response(['permalink' => 'https://www.instagram.com/p/m/']),
        ]);
        $category = Category::factory()->create(['name' => 'Sports']);

        $this->actingAs($this->admin())->post('/admin/posts', ['title' => 'Auto shared', 'category_id' => $category->id, 'status' => 'published', 'save_as' => 'publish'])->assertRedirect();
        $share = SocialShare::first();
        $this->assertNotNull($share);
        $this->assertSame('Auto shared | Sports #vd', $share->caption);

        $this->assertSame('Auto shared | Sports #vd', app(InstagramPublisher::class)->caption(Post::first()));
    }

    public function test_instagram_settings_and_connection_test(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/settings?tab=instagram')->assertOk()->assertSee('Instagram page connection');
        $this->actingAs($admin)->put('/admin/settings', ['site_name' => 'ViralDose', 'posts_per_page' => 12, 'post_url_format' => 'category', 'organization_type' => 'NewsMediaOrganization', 'language' => 'en', 'storage_driver' => 'public',
            'instagram_business_id' => '17841400000000000', 'instagram_access_token' => 'SECRET', 'instagram_username' => 'viraldose_news', 'tab' => 'instagram'])->assertRedirect('/admin/settings?tab=instagram');
        $this->assertSame('SECRET', app(InstagramPublisher::class)->token());

        Http::fake(['graph.facebook.com/*' => Http::response(['username' => 'viraldose_news', 'followers_count' => 12000])]);
        $this->actingAs($admin)->post('/admin/settings/test-instagram')->assertRedirect()->assertSessionHas('status');
    }

    // ---- Ads & code injection -------------------------------------------

    public function test_ads_render_in_new_slots_with_device_and_page_targeting(): void
    {
        $post = $this->publishedPost();
        Post::factory()->count(12)->create(['category_id' => $post->category_id]);
        Ad::create(['name' => 'Hero', 'slot' => 'home_after_hero', 'device' => 'mobile', 'pages' => 'home', 'code' => '<b>HERO-AD</b>', 'is_active' => true]);
        Ad::create(['name' => 'Feed', 'slot' => 'home_in_latest', 'code' => '<b>FEED-AD</b>', 'is_active' => true]);
        Ad::create(['name' => 'Grid', 'slot' => 'archive_in_grid', 'pages' => 'category', 'code' => '<b>GRID-AD</b>', 'is_active' => true]);
        Ad::create(['name' => 'Sticky', 'slot' => 'mobile_sticky', 'code' => '<b>STICKY-AD</b>', 'is_active' => true]);
        Ad::create(['name' => 'Related', 'slot' => 'post_after_related', 'device' => 'desktop', 'code' => '<b>RELATED-AD</b>', 'is_active' => true]);

        $this->get('/')->assertOk()->assertSee('HERO-AD', false)->assertSee('md:hidden', false)->assertSee('FEED-AD', false)->assertSee('STICKY-AD', false)->assertDontSee('GRID-AD', false);
        $this->get('/category/sports')->assertOk()->assertSee('GRID-AD', false)->assertDontSee('HERO-AD', false);
        $this->get($post->url())->assertOk()->assertSee('RELATED-AD', false)->assertSee('hidden md:block', false);

        Setting::set('ads_enabled', 0);
        $this->get('/')->assertOk()->assertDontSee('HERO-AD', false)->assertDontSee('STICKY-AD', false);
    }

    public function test_header_and_body_code_and_custom_css_are_injected(): void
    {
        Setting::setMany(['head_scripts' => '<meta name="x-head" content="1">', 'body_start_scripts' => '<noscript id="gtm-body"></noscript>', 'body_scripts' => '<script id="chat-widget"></script>', 'custom_css' => '.cat-badge{border-radius:0}']);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="x-head" content="1">', $html);
        $this->assertLessThan(strpos($html, 'Skip to content'), strpos($html, 'id="gtm-body"'));
        $this->assertStringContainsString('id="chat-widget"', $html);
        $this->assertStringContainsString('<style id="custom-css">.cat-badge{border-radius:0}</style>', $html);
        $this->actingAs($this->admin())->get('/admin/settings?tab=header')->assertOk()->assertSee('Header code');
        $this->actingAs($this->admin())->get('/admin/settings?tab=body')->assertOk()->assertSee('Before &lt;/body&gt;', false)->assertDontSee('&amp;lt;', false);
    }
}
