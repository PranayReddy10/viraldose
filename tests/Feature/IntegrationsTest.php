<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IndexingLog;
use App\Models\Post;
use App\Models\RssFeed;
use App\Models\Setting;
use App\Models\User;
use App\Services\FeedImporter;
use App\Services\Google\GoogleClient;
use App\Services\ImageService;
use App\Services\IndexNow;
use App\Services\SeoAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    private const RSS = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:media="http://search.yahoo.com/mrss/">
<channel><title>Example</title>
<item><title>First &amp; Best Story</title><link>https://example.com/a</link><guid>guid-a</guid><pubDate>Mon, 01 Sep 2026 10:00:00 +0530</pubDate>
<description><![CDATA[Short summary A]]></description><content:encoded><![CDATA[<p>Body A <script>x()</script><img src="https://example.com/a.jpg"></p>]]></content:encoded><category>Cricket</category></item>
<item><title>Second Story</title><link>https://example.com/b</link><guid>guid-b</guid><media:content url="https://example.com/b.jpg" medium="image"/><description>Summary B</description></item>
</channel></rss>
XML;

    private function serviceAccountJson(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);

        return json_encode([
            'type' => 'service_account',
            'client_email' => 'bot@project.iam.gserviceaccount.com',
            'private_key' => $pem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);
    }

    public function test_rss_import_creates_drafts_deduplicates_and_sanitizes(): void
    {
        Http::fake(['example.com/feed' => Http::response(self::RSS, 200), 'example.com/*' => Http::response('<html><body><p>Full body for the second story, long enough to be an article paragraph of its own.</p><p>Second paragraph.</p></body></html>', 200)]);
        $feed = RssFeed::create(['name' => 'Example', 'url' => 'https://example.com/feed', 'category_id' => Category::factory()->create()->id, 'user_id' => User::factory()->create()->id, 'max_items' => 10]);

        $count = app(FeedImporter::class)->import($feed);

        $this->assertSame(2, $count);
        $a = Post::where('feed_guid', 'guid-a')->first();
        $this->assertSame('First & Best Story', $a->title);
        $this->assertSame('draft', $a->status);
        $this->assertSame('https://example.com/a.jpg', $a->image);
        $this->assertStringNotContainsString('<script', $a->content);
        $this->assertSame('https://example.com/a', $a->source_url);
        $this->assertSame(['Cricket'], $a->tags->pluck('name')->all());
        $this->assertSame('https://example.com/b.jpg', Post::where('feed_guid', 'guid-b')->value('image'));
        $this->assertStringContainsString('Full body for the second story', Post::where('feed_guid', 'guid-b')->value('content'));

        // Second run imports nothing new.
        $this->assertSame(0, app(FeedImporter::class)->import($feed->fresh()));
        $this->assertSame(2, Post::count());
    }

    public function test_rss_auto_publish_pings_indexnow(): void
    {
        Http::fake([
            'example.com/feed' => Http::response(self::RSS, 200),
            'example.com/*' => Http::response('', 404),
            'api.indexnow.org/*' => Http::response('', 202),
        ]);
        $feed = RssFeed::create(['name' => 'Example', 'url' => 'https://example.com/feed', 'category_id' => Category::factory()->create()->id, 'user_id' => User::factory()->create()->id, 'auto_publish' => true, 'max_items' => 1]);

        app(FeedImporter::class)->import($feed);

        $this->assertSame('published', Post::first()->status);
        $this->assertDatabaseHas('indexing_logs', ['provider' => 'indexnow', 'status' => 'ok']);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'indexnow') && $req['host'] === 'localhost');
    }

    public function test_feed_import_records_errors(): void
    {
        Http::fake(['bad.example/*' => Http::response('nope', 500)]);
        $feed = RssFeed::create(['name' => 'Bad', 'url' => 'https://bad.example/rss', 'category_id' => Category::factory()->create()->id, 'user_id' => User::factory()->create()->id]);
        $this->assertSame(0, app(FeedImporter::class)->import($feed));
        $this->assertStringContainsString('HTTP 500', $feed->fresh()->last_error);
    }

    public function test_indexnow_key_file_is_served(): void
    {
        $key = IndexNow::key();
        $this->get("/{$key}.txt")->assertOk()->assertSee($key);
        $this->get('/'.str_repeat('a', 32).'.txt')->assertNotFound();
    }

    public function test_publishing_a_post_submits_to_google_and_indexnow(): void
    {
        Storage::fake('local');
        GoogleClient::storeKey($this->serviceAccountJson());
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'indexing.googleapis.com/*' => Http::response(['urlNotificationMetadata' => ['url' => 'x']]),
            'api.indexnow.org/*' => Http::response('', 200),
        ]);
        $admin = $this->admin();
        $category = Category::factory()->create(['slug' => 'news']);

        $this->actingAs($admin)->post('/admin/posts', ['title' => 'Breaking story', 'category_id' => $category->id, 'content' => '<p>Hello</p>', 'status' => 'draft', 'save_as' => 'publish'])
            ->assertRedirect();

        $post = Post::first();
        $this->assertTrue($post->isPublished());
        $this->assertNotNull($post->indexing_requested_at);
        $this->assertSame(2, IndexingLog::where('post_id', $post->id)->where('status', 'ok')->count());
        Http::assertSent(fn ($req) => str_contains($req->url(), 'urlNotifications:publish') && $req['type'] === 'URL_UPDATED' && $req['url'] === $post->url());
        Http::assertSent(fn ($req) => $req->url() === 'https://oauth2.googleapis.com/token' && str_contains($req['scope'] ?? $req->data()['assertion'] ?? '', '') !== null);
    }

    public function test_google_jwt_is_rs256_signed_with_expected_claims(): void
    {
        Storage::fake('local');
        $json = $this->serviceAccountJson();
        GoogleClient::storeKey($json);
        $client = new GoogleClient;
        $jwt = $client->jwt(['iss' => 'bot', 'scope' => 's', 'aud' => 'a', 'iat' => 1, 'exp' => 2], json_decode($json, true)['private_key']);
        [$h, $p, $sig] = explode('.', $jwt);
        $this->assertSame(['alg' => 'RS256', 'typ' => 'JWT'], json_decode(base64_decode(strtr($h, '-_', '+/')), true));
        $this->assertSame('bot', json_decode(base64_decode(strtr($p, '-_', '+/')), true)['iss']);
        $publicKey = openssl_pkey_get_details(openssl_pkey_get_private(json_decode($json, true)['private_key']))['key'];
        $this->assertSame(1, openssl_verify("$h.$p", base64_decode(strtr($sig, '-_', '+/')), $publicKey, OPENSSL_ALGO_SHA256));
    }

    public function test_url_inspection_updates_post_index_status(): void
    {
        Storage::fake('local');
        GoogleClient::storeKey($this->serviceAccountJson());
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok']),
            'searchconsole.googleapis.com/*' => Http::response(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'NEUTRAL', 'coverageState' => 'Crawled - currently not indexed', 'lastCrawlTime' => '2026-09-21T10:00:00Z', 'robotsTxtState' => 'ALLOWED', 'pageFetchState' => 'SUCCESSFUL']]]),
        ]);
        $post = $this->publishedPost();

        $this->actingAs($this->admin())->post(route('admin.posts.inspect', $post))->assertRedirect()->assertSessionHas('status');
        $post->refresh();
        $this->assertSame('NEUTRAL', $post->index_status);
        $this->assertSame('Crawled - currently not indexed', $post->index_coverage);
        $this->assertNotNull($post->last_crawled_at);
        $this->assertDatabaseHas('indexing_logs', ['provider' => 'search_console', 'action' => 'inspect', 'status' => 'ok']);
    }

    public function test_google_dashboard_renders_with_mocked_apis_and_without_key(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/google')->assertOk()->assertSee('Connect Google');

        Storage::fake('local');
        GoogleClient::storeKey($this->serviceAccountJson());
        Setting::set('ga4_property_id', '123');
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok']),
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['rows' => [['keys' => ['2026-09-01'], 'clicks' => 10, 'impressions' => 200, 'ctr' => 0.05, 'position' => 8.2]]]),
            'www.googleapis.com/webmasters/v3/sites/*/sitemaps' => Http::response(['sitemap' => [['path' => 'http://localhost/sitemap.xml', 'lastSubmitted' => '2026-09-01T00:00:00Z', 'errors' => 0, 'warnings' => 0]]]),
            'analyticsdata.googleapis.com/*' => Http::response(['rows' => [['dimensionValues' => [['value' => '20260901']], 'metricValues' => [['value' => '5'], ['value' => '6'], ['value' => '7']]]]]),
        ]);
        $this->actingAs($admin)->get('/admin/google')->assertOk()->assertSee('Total clicks')->assertSee('200');
        $this->actingAs($admin)->get('/admin/google?tab=analytics')->assertOk()->assertSee('Users per day');
        $this->actingAs($admin)->get('/admin/google?tab=indexing')->assertOk()->assertSee('Bulk submit');
        $this->actingAs($admin)->get('/admin/google?tab=sitemaps')->assertOk()->assertSee('sitemap.xml');
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Impressions');
    }

    public function test_sitemap_submission_sends_an_empty_body(): void
    {
        Storage::fake('local');
        GoogleClient::storeKey($this->serviceAccountJson());
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok']),
            'www.googleapis.com/webmasters/v3/sites/*/sitemaps/*' => Http::response('', 204),
        ]);

        $this->actingAs($this->admin())->post(route('admin.google.sitemaps'))
            ->assertRedirect()->assertSessionHas('status')->assertSessionHasNoErrors();

        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), '/sitemaps/') && str_contains($r->url(), rawurlencode(route('sitemap.index'))) && $r->body() === '');
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), rawurlencode(route('sitemap.news'))) && $r->body() === '');
        Http::assertNotSent(fn ($r) => $r->method() === 'PUT' && $r->body() === '[]');
        $this->assertDatabaseHas('indexing_logs', ['provider' => 'search_console', 'action' => 'sitemap', 'status' => 'ok']);
    }

    public function test_seo_analyzer_scores_and_flags_thin_content(): void
    {
        $thin = $this->publishedPost(['content' => '<p>Short.</p>', 'image' => null, 'meta_keywords' => 'india']);
        $result = app(SeoAnalyzer::class)->analyze($thin->load('tags'));
        $this->assertLessThan(60, $result['score']);
        $labels = collect($result['checks'])->keyBy('label');
        $this->assertSame('fail', $labels['Content length']['status']);
        $this->assertSame('fail', $labels['Featured image']['status']);

        $rich = Post::factory()->create([
            'category_id' => $thin->category_id, 'title' => 'India wins the final in a thrilling match tonight', 'meta_keywords' => 'india',
            'meta_description' => str_repeat('India wins the final. ', 5), 'image' => 'uploads/x.jpg', 'image_alt' => 'India team',
            'content' => '<p>India '.str_repeat('word ', 450).'</p><h2>Match</h2><p><a href="/sports/other">related</a> <a href="https://bcci.tv">BCCI</a></p>',
        ]);
        $this->assertGreaterThanOrEqual(80, app(SeoAnalyzer::class)->analyze($rich->load('tags'))['score']);
    }

    public function test_gallery_and_file_uploads_show_on_article(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $category = Category::factory()->create(['slug' => 'news']);

        $this->actingAs($admin)->post('/admin/posts', [
            'title' => 'Photo story', 'category_id' => $category->id, 'status' => 'published', 'content' => '<p>Hi</p>',
            'gallery' => [UploadedFile::fake()->image('g1.jpg', 900, 600), UploadedFile::fake()->image('g2.jpg', 900, 600)],
            'files' => [UploadedFile::fake()->create('brochure.pdf', 120, 'application/pdf')],
        ])->assertRedirect();

        $post = Post::first();
        $this->assertCount(2, $post->images);
        $this->assertCount(1, $post->files);
        $this->get($post->url())->assertOk()->assertSee('Gallery')->assertSee('brochure.pdf');
        $this->get(route('post.file.download', $post->files->first()))->assertRedirect();
        $this->assertSame(1, $post->files->first()->fresh()->downloads);

        $this->actingAs($admin)->delete(route('admin.posts.images.destroy', $post->images->first()))->assertRedirect();
        $this->assertCount(1, $post->fresh()->images);
    }

    public function test_spaces_storage_driver_prefixes_paths_and_builds_cdn_urls(): void
    {
        Storage::fake('spaces', ['url' => 'https://cdn.example.com']);
        Setting::setMany(['storage_driver' => 'spaces', 'spaces_bucket' => 'viraldose']);

        $service = app(ImageService::class);
        $ref = $service->store(UploadedFile::fake()->image('photo.jpg', 1000, 700), 'uploads/posts');

        $this->assertStringStartsWith('spaces://uploads/posts/', $ref);
        Storage::disk('spaces')->assertExists(substr($ref, 9));
        $info = pathinfo(substr($ref, 9));
        Storage::disk('spaces')->assertExists($info['dirname'].'/'.$info['filename'].'-medium.webp');
        $this->assertStringStartsWith('https://cdn.example.com/uploads/posts/', ImageService::url($ref, 'medium'));
        $this->assertStringEndsWith('-medium.webp', ImageService::url($ref, 'medium'));

        $service->delete($ref);
        Storage::disk('spaces')->assertMissing(substr($ref, 9));
    }

    public function test_rss_feed_admin_crud_and_fetch(): void
    {
        Http::fake(['example.com/feed' => Http::response(self::RSS, 200), 'example.com/*' => Http::response('', 404)]);
        $admin = $this->admin();
        $category = Category::factory()->create();

        $this->actingAs($admin)->get('/admin/feeds')->assertOk();
        $this->actingAs($admin)->post('/admin/feeds', ['name' => 'Example', 'url' => 'https://example.com/feed', 'category_id' => $category->id, 'user_id' => $admin->id, 'language' => 'en', 'max_items' => 5])->assertRedirect('/admin/feeds');
        $feed = RssFeed::first();
        $this->actingAs($admin)->post(route('admin.feeds.fetch', $feed))->assertRedirect()->assertSessionHas('status');
        $this->assertSame(2, $feed->posts()->count());
        $this->actingAs($admin)->postJson('/admin/feeds/preview', ['url' => 'https://example.com/feed'])->assertOk()->assertJsonCount(2, 'items');
        $this->actingAs($admin)->get(route('admin.feeds.edit', $feed))->assertOk();
    }

    public function test_settings_store_google_key_and_spaces_secret_encrypted(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/settings', [
            'site_name' => 'ViralDose', 'posts_per_page' => 12, 'post_url_format' => 'category', 'organization_type' => 'NewsMediaOrganization', 'language' => 'en',
            'storage_driver' => 'spaces', 'spaces_key' => 'KEY', 'spaces_secret' => 'SECRET', 'spaces_bucket' => 'bucket', 'spaces_region' => 'blr1',
            'ga4_property_id' => '555', 'google_service_account' => UploadedFile::fake()->createWithContent('sa.json', $this->serviceAccountJson()),
        ])->assertRedirect();

        $this->assertTrue((new GoogleClient)->isConfigured());
        $this->assertNotSame('SECRET', Setting::get('spaces_secret'));
        $this->assertSame('SECRET', Crypt::decryptString(Setting::get('spaces_secret')));
        $this->assertSame('spaces', setting('storage_driver'));

        $this->actingAs($admin)->put('/admin/settings', ['site_name' => 'ViralDose', 'posts_per_page' => 12, 'post_url_format' => 'category', 'organization_type' => 'NewsMediaOrganization', 'language' => 'en', 'storage_driver' => 'public', 'google_service_account' => UploadedFile::fake()->createWithContent('bad.json', '{"type":"nope"}')])
            ->assertSessionHasErrors('google_service_account');
    }

    public function test_settings_test_buttons_work_when_submitted_from_the_put_form(): void
    {
        $admin = $this->admin();
        Setting::setMany(['spaces_bucket' => 'viraldose', 'spaces_key' => 'k', 'spaces_secret' => Crypt::encryptString('s'), 'spaces_endpoint' => 'https://blr1.digitaloceanspaces.com']);
        Storage::fake('spaces', ['url' => 'https://viraldose.blr1.digitaloceanspaces.com']);

        // Browser sends POST with the surrounding form's _method=PUT spoof field.
        $this->actingAs($admin)->post('/admin/settings/test-storage', ['_method' => 'PUT'])
            ->assertRedirect('/admin/settings?tab=storage')->assertSessionHas('status');
        $this->assertStringContainsString('connected', strtolower(session('status')));

        $this->actingAs($admin)->post('/admin/settings/test-instagram', ['_method' => 'PUT'])
            ->assertRedirect()->assertSessionHasErrors('instagram_business_id'); // not configured, but no 405
    }
}
