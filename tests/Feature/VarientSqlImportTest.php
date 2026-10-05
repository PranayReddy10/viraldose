<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Support\SqlDumpReader;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
