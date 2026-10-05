<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Services\RemoteImageFetcher;
use App\Support\SqlDumpReader;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VarientSqlImportTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture = __DIR__.'/../fixtures/varient-posts.sql';

    public function test_sql_dump_reader_parses_escaped_strings_nulls_and_multiple_statements(): void
    {
        $reader = new SqlDumpReader($this->fixture);
        $this->assertSame(['posts'], $reader->tables());
        $rows = iterator_to_array($reader->rows('posts'), false);

        $this->assertCount(4, $rows);
        $this->assertSame('1', $rows[0]['id']);
        $this->assertSame('Atlético Madrid vs Barcelona: Match Highlights', $rows[0]['title']);
        $this->assertStringContainsString('It\'s a "great" night.', $rows[0]['content']);
        $this->assertStringContainsString("\r\n", $rows[0]['content']);
        $this->assertNull($rows[0]['image_id']);
        $this->assertNull($rows[1]['keywords']);
        $this->assertSame('Alt text here', $rows[1]['image_description']);
        $this->assertSame('video', $rows[2]['post_type']);
    }

    public function test_import_creates_posts_with_mapping_cleaning_tags_and_update_mode(): void
    {
        $this->seed(CategorySeeder::class);
        $this->admin();
        config(['varient-import.category_map' => [9 => 'sports', 3 => 'entertainment']]);

        $this->artisan('import:varient-sql', ['file' => $this->fixture, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Post::count());

        $this->artisan('import:varient-sql', ['file' => $this->fixture])->assertSuccessful();
        $this->assertSame(4, Post::count());

        $match = Post::where('legacy_id', 1)->firstOrFail();
        $this->assertSame('atletico-madrid-vs-barcelona-match-highlights', $match->slug);
        $this->assertSame('sports', $match->category->slug);
        $this->assertSame('published', $match->status);
        $this->assertSame('2026-04-05 00:09:33', $match->published_at->format('Y-m-d H:i:s'));
        $this->assertSame(120, $match->views);
        $this->assertSame('https://images.example.com/match.jpg', $match->image);
        $this->assertStringNotContainsString('data-start', $match->content);
        $this->assertStringNotContainsString('style=', $match->content);
        $this->assertStringNotContainsString('<script', $match->content);
        $this->assertStringContainsString('<strong>Madrid, Spain:</strong>', $match->content);
        $this->assertStringNotContainsString('<p>&nbsp;</p>', $match->content);
        $this->assertEqualsCanonicalizing(['la liga', 'barcelona', 'atletico madrid'], $match->tags->pluck('name')->all());

        $unknown = Post::where('legacy_id', 2)->firstOrFail();
        $this->assertSame('news', $unknown->category->slug); // default category
        $this->assertSame('Alt text here', $unknown->image_alt);
        $this->assertNull($unknown->image);

        $video = Post::where('legacy_id', 3)->firstOrFail();
        $this->assertSame('video', $video->post_type);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $video->video_url);
        $this->assertSame('https://source.example.com/orig', $video->source_url);

        $this->assertSame('draft', Post::where('legacy_id', 4)->value('status'));

        // Old flat URL keeps working through the automatic 301.
        $this->get('/atletico-madrid-vs-barcelona-match-highlights')->assertRedirect('http://localhost/sports/atletico-madrid-vs-barcelona-match-highlights');

        // Second run is idempotent; --update moves unmapped posts once a mapping is supplied.
        $this->artisan('import:varient-sql', ['file' => $this->fixture])->assertSuccessful();
        $this->assertSame(4, Post::count());
        $this->artisan('import:varient-sql', ['file' => $this->fixture, '--update' => true, '--category-map' => '99:world'])->assertSuccessful();
        $this->assertSame('world', $unknown->fresh()->category->slug);
        $this->assertTrue(Category::where('slug', 'world')->exists());
    }

    public function test_download_images_stores_remote_featured_images_locally(): void
    {
        Storage::fake('public');
        $this->seed(CategorySeeder::class);
        $this->admin();
        $jpeg = (function () {
            ob_start();
            imagejpeg(imagecreatetruecolor(900, 600));

            return ob_get_clean();
        })();
        Http::fake(['images.example.com/*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg'])]);

        $this->artisan('import:varient-sql', ['file' => $this->fixture, '--download-images' => true])->assertSuccessful();

        $post = Post::where('legacy_id', 1)->firstOrFail();
        $this->assertStringStartsWith('uploads/imported/2026/04/', $post->image);
        Storage::disk('public')->assertExists($post->image);
        $info = pathinfo($post->image);
        Storage::disk('public')->assertExists($info['dirname'].'/'.$info['filename'].'-medium.webp');
    }

    public function test_admin_import_page_uploads_and_runs_the_importer(): void
    {
        Storage::fake('local');
        $this->seed(CategorySeeder::class);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/import')->assertOk()->assertSee('Upload the Varient export');
        $this->actingAs($admin)->post('/admin/import/upload', ['file' => new UploadedFile($this->fixture, 'posts.sql', 'text/plain', null, true)])->assertRedirect();
        Storage::disk('local')->assertExists('imports/posts.sql');

        $this->actingAs($admin)->post('/admin/import/run', ['file' => 'posts.sql', 'mode' => 'dry'])->assertRedirect('/admin/import');
        $this->assertSame(0, Post::count());

        $this->actingAs($admin)->post('/admin/import/run', ['file' => 'posts.sql', 'mode' => 'import', 'category_map' => '9:sports, 3:entertainment', 'default_category' => 'news'])
            ->assertRedirect('/admin/import')->assertSessionHas('import_output');
        $this->assertSame(4, Post::count());
        $this->actingAs($admin)->get('/admin/import')->assertOk()->assertSee('Finished')->assertSee('4');

        $this->actingAs($admin)->post('/admin/import/run', ['file' => '../../.env', 'mode' => 'dry'])->assertSessionHasErrors('file');
        $this->actingAs($admin)->delete('/admin/import/posts.sql')->assertRedirect();
        Storage::disk('local')->assertMissing('imports/posts.sql');
    }

    public function test_remote_image_fetcher_downloads_records_failures_and_retries(): void
    {
        Storage::fake('public');
        $jpeg = (function () {
            ob_start();
            imagejpeg(imagecreatetruecolor(900, 600));

            return ob_get_clean();
        })();
        Http::fake([
            'cdn.good.example/*' => Http::response($jpeg, 200, ['Content-Type' => 'application/octet-stream']), // wrong header, real image
            'cdn.blocked.example/*' => Http::sequence()->push('forbidden', 403)->push($jpeg, 200, ['Content-Type' => 'image/jpeg']),
            'cdn.html.example/*' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'text/html']),
        ]);
        $category = Category::factory()->create();
        $good = Post::factory()->create(['category_id' => $category->id, 'image' => 'https://cdn.good.example/a/photo.jpg?x=1']);
        $blocked = Post::factory()->create(['category_id' => $category->id, 'image' => 'https://cdn.blocked.example/b.jpg']);
        $html = Post::factory()->create(['category_id' => $category->id, 'image' => 'https://cdn.html.example/c.jpg']);
        Post::factory()->create(['category_id' => $category->id, 'image' => 'uploads/local.jpg']);

        $this->artisan('images:fetch-remote', ['--limit' => 10])->assertSuccessful();

        $good->refresh();
        $this->assertStringStartsWith('uploads/imported/', $good->image);
        Storage::disk('public')->assertExists($good->image);
        $this->assertStringContainsString('HTTP 403', $blocked->fresh()->image_fetch_error);
        $this->assertStringContainsString('not an image', $html->fresh()->image_fetch_error);
        $this->assertStringStartsWith('https://', $blocked->fresh()->image); // remote URL kept as fallback
        $this->assertSame(0, RemoteImageFetcher::pendingQuery()->whereNull('image_fetch_error')->count());

        // Retry after the source starts responding (second response in the sequence).
        $this->artisan('images:fetch-remote', ['--retry' => true])->assertSuccessful();
        $this->assertStringStartsWith('uploads/imported/', $blocked->fresh()->image);
        $this->assertNull($blocked->fresh()->image_fetch_error);

        // Admin button runs the same fetcher.
        $this->actingAs($this->admin())->post('/admin/import/fetch-images', ['retry' => 1])->assertRedirect('/admin/import')->assertSessionHas('import_output');
    }

    public function test_import_continues_when_a_row_fails_and_unwraps_div_soup(): void
    {
        $this->seed(CategorySeeder::class);
        $this->admin();
        $fixture = sys_get_temp_dir().'/varient-broken.sql';
        file_put_contents($fixture, str_replace(
            "'<p>Body two</p>'",
            "'<div><div><h2>Heading</h2><p>Body two</p></div></div>'",
            str_replace("(2, 1, 'Unknown category story'", '(2, 1, NULL', file_get_contents($this->fixture)),
        ));

        $this->artisan('import:varient-sql', ['file' => $fixture])->assertSuccessful();

        // Row 2 has a NULL title (required) -> failed, the other three imported.
        $this->assertSame(3, Post::count());
        $this->assertNull(Post::where('legacy_id', 2)->first());
        $this->assertStringNotContainsString('<div>', Post::where('legacy_id', 1)->value('content'));
        @unlink($fixture);
    }

    public function test_remote_fetcher_accepts_webp_and_falls_back_from_avif(): void
    {
        Storage::fake('public');
        $img = imagecreatetruecolor(800, 500);
        ob_start();
        imagewebp($img, null, 80);
        $webp = ob_get_clean();
        ob_start();
        imagejpeg($img, null, 85);
        $jpeg = ob_get_clean();
        $avif = "\x00\x00\x00\x1cftypavif\x00\x00\x00\x00avifmif1miaf".str_repeat("\x00", 3000);
        Http::fake([
            'cdn.webp.example/*' => Http::response($webp, 200, ['Content-Type' => 'image/webp']),
            'cdn.avif.example/*' => Http::sequence()->push($avif, 200, ['Content-Type' => 'image/avif'])->push($jpeg, 200, ['Content-Type' => 'image/jpeg']),
            'cdn.svg.example/*' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"><script>x()</script></svg>'.str_repeat(' ', 3000), 200, ['Content-Type' => 'image/svg+xml']),
        ]);
        $category = Category::factory()->create();
        $w = Post::factory()->create(['category_id' => $category->id, 'image' => 'https://cdn.webp.example/photo.webp']);
        $a = Post::factory()->create(['category_id' => $category->id, 'image' => 'https://cdn.avif.example/photo']);
        $s = Post::factory()->create(['category_id' => $category->id, 'image' => 'https://cdn.svg.example/logo.svg']);

        $this->artisan('images:fetch-remote', ['--limit' => 10])->assertSuccessful();

        $this->assertStringEndsWith('.webp', $w->fresh()->image);
        Storage::disk('public')->assertExists($w->fresh()->image);
        $info = pathinfo($w->fresh()->image);
        Storage::disk('public')->assertExists($info['dirname'].'/'.$info['filename'].'-medium.webp');

        $this->assertStringEndsWith('.jpg', $a->fresh()->image);
        Http::assertSentCount(4);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'cdn.avif.example') && $r->header('Accept')[0] === 'image/jpeg,image/png;q=0.9');

        $this->assertStringStartsWith('https://', $s->fresh()->image);
        $this->assertStringContainsString('not an image', $s->fresh()->image_fetch_error);
    }

    public function test_webp_featured_image_upload_is_accepted(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create(['slug' => 'news']);
        $img = imagecreatetruecolor(1000, 600);
        $tmp = tempnam(sys_get_temp_dir(), 'vd').'.webp';
        imagewebp($img, $tmp, 80);

        $this->actingAs($this->admin())->post('/admin/posts', ['title' => 'WebP story', 'category_id' => $category->id, 'status' => 'published',
            'image' => new UploadedFile($tmp, 'photo.webp', 'image/webp', null, true)])->assertRedirect();
        $post = Post::first();
        $this->assertStringEndsWith('.webp', $post->image);
        $info = pathinfo($post->image);
        Storage::disk('public')->assertExists($info['dirname'].'/'.$info['filename'].'-large.webp');
        @unlink($tmp);
    }
}
