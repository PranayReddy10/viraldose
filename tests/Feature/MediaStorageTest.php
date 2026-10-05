<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Setting;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageTest extends TestCase
{
    use RefreshDatabase;

    private function selectSpaces(): void
    {
        Setting::setMany(['storage_driver' => 'spaces', 'spaces_bucket' => 'viraldose', 'spaces_key' => 'k', 'spaces_region' => 'blr1', 'spaces_endpoint' => 'https://blr1.digitaloceanspaces.com']);
    }

    public function test_new_uploads_go_to_spaces_when_selected_and_update_without_file_keeps_image(): void
    {
        Storage::fake('spaces', ['url' => 'https://viraldose.blr1.cdn.digitaloceanspaces.com']);
        Storage::fake('public');
        $this->selectSpaces();
        $admin = $this->admin();
        $category = Category::factory()->create(['slug' => 'news']);

        $this->actingAs($admin)->post('/admin/posts', ['title' => 'Spaces story', 'category_id' => $category->id, 'status' => 'published',
            'image' => UploadedFile::fake()->image('a.jpg', 1300, 800)])->assertRedirect();
        $post = Post::first();
        $this->assertStringStartsWith('spaces://uploads/posts/', $post->image);
        Storage::disk('spaces')->assertExists(substr($post->image, 9));
        $this->assertStringStartsWith('https://viraldose.blr1.cdn.digitaloceanspaces.com/uploads/posts/', $post->imageUrl('large'));
        $this->assertStringEndsWith('-large.webp', $post->imageUrl('large'));

        // Plain "Update" with no new file must not touch the image.
        $this->actingAs($admin)->put(route('admin.posts.update', $post), ['title' => 'Spaces story edited', 'category_id' => $category->id, 'status' => 'published', 'image_url' => '', 'remove_image' => 0])
            ->assertRedirect();
        $this->assertSame($post->image, $post->fresh()->image);
        Storage::disk('spaces')->assertExists(substr($post->image, 9));
        $this->get($post->fresh()->url())->assertOk()->assertSee('cdn.digitaloceanspaces.com/uploads/posts/', false);

        // Replacing the image: new file stored first, old one removed afterwards.
        $old = $post->image;
        $this->actingAs($admin)->put(route('admin.posts.update', $post), ['title' => 'Spaces story edited', 'category_id' => $category->id, 'status' => 'published',
            'image' => UploadedFile::fake()->image('b.png', 900, 600)])->assertRedirect();
        $this->assertNotSame($old, $post->fresh()->image);
        Storage::disk('spaces')->assertMissing(substr($old, 9));
        Storage::disk('spaces')->assertExists(substr($post->fresh()->image, 9));
    }

    public function test_failed_spaces_upload_keeps_the_old_image_and_shows_the_reason(): void
    {
        Storage::fake('public');
        // Spaces selected but pointing at a dead endpoint: the write must fail loudly, not silently.
        Setting::setMany(['storage_driver' => 'spaces', 'spaces_bucket' => 'viraldose', 'spaces_key' => 'k', 'spaces_secret' => Crypt::encryptString('s'), 'spaces_region' => 'blr1', 'spaces_endpoint' => 'https://127.0.0.1:9']);
        config(['filesystems.disks.spaces.endpoint' => 'https://127.0.0.1:9', 'filesystems.disks.spaces.key' => 'k', 'filesystems.disks.spaces.secret' => 's', 'filesystems.disks.spaces.bucket' => 'viraldose', 'filesystems.disks.spaces.throw' => true]);
        $admin = $this->admin();
        $post = $this->publishedPost(['image' => 'uploads/posts/existing.jpg']);
        Storage::disk('public')->put('uploads/posts/existing.jpg', 'x');

        $response = $this->actingAs($admin)->from(route('admin.posts.edit', $post))->put(route('admin.posts.update', $post), [
            'title' => $post->title, 'category_id' => $post->category_id, 'status' => 'published',
            'image' => UploadedFile::fake()->image('new.jpg', 800, 600),
        ]);

        $response->assertRedirect(route('admin.posts.edit', $post))->assertSessionHasErrors('image');
        $this->assertStringContainsString('DigitalOcean Spaces', session('errors')->first('image'));
        $this->assertSame('uploads/posts/existing.jpg', $post->fresh()->image);
        Storage::disk('public')->assertExists('uploads/posts/existing.jpg');
    }

    public function test_unscheduling_a_future_post_publishes_it_now(): void
    {
        $admin = $this->admin();
        $category = Category::factory()->create();
        $post = Post::factory()->scheduled()->create(['category_id' => $category->id]);
        $this->assertTrue($post->isScheduled());

        $this->actingAs($admin)->put(route('admin.posts.update', $post), ['title' => $post->title, 'category_id' => $category->id, 'status' => 'published', 'scheduled' => 0, 'published_at' => $post->published_at->format('Y-m-d\TH:i')])->assertRedirect();
        $this->assertTrue($post->fresh()->isPublished());

        // Keeping "Scheduled" ticked keeps the future date.
        $future = Post::factory()->scheduled()->create(['category_id' => $category->id]);
        $this->actingAs($admin)->put(route('admin.posts.update', $future), ['title' => $future->title, 'category_id' => $category->id, 'status' => 'published', 'scheduled' => 1, 'published_at' => $future->published_at->format('Y-m-d\TH:i')])->assertRedirect();
        $this->assertTrue($future->fresh()->isScheduled());
    }

    public function test_remote_variant_lookup_falls_back_to_original_when_variants_are_missing(): void
    {
        Storage::fake('spaces', ['url' => 'https://cdn.example.com']);
        Storage::disk('spaces')->put('uploads/legacy/photo.jpg', 'x'); // no -large.webp next to it
        $this->assertSame('https://cdn.example.com/uploads/legacy/photo.jpg', ImageService::url('spaces://uploads/legacy/photo.jpg', 'large'));
    }

    public function test_remote_images_are_fetched_into_spaces_when_selected(): void
    {
        Storage::fake('spaces', ['url' => 'https://cdn.example.com']);
        Storage::fake('public');
        $this->selectSpaces();
        $jpeg = (function () {
            ob_start();
            imagejpeg(imagecreatetruecolor(900, 600));

            return ob_get_clean();
        })();
        Http::fake(['img.example.com/*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg'])]);
        $post = $this->publishedPost(['image' => 'https://img.example.com/a.jpg']);

        $this->artisan('images:fetch-remote')->assertSuccessful();

        $post->refresh();
        $this->assertStringStartsWith('spaces://uploads/imported/', $post->image);
        Storage::disk('spaces')->assertExists(substr($post->image, 9));
        $this->assertStringStartsWith('https://cdn.example.com/uploads/imported/', $post->imageUrl('medium'));
    }

    public function test_remove_image_is_not_undone_by_the_prefilled_image_url_field(): void
    {
        $admin = $this->admin();
        $post = $this->publishedPost(['image' => 'https://img.example.com/old.jpg']);

        $this->actingAs($admin)->put(route('admin.posts.update', $post), [
            'title' => $post->title, 'category_id' => $post->category_id, 'status' => 'published',
            'remove_image' => 1, 'image_url' => 'https://img.example.com/old.jpg', // the form pre-fills the current URL
        ])->assertRedirect();
        $this->assertNull($post->fresh()->image);

        $this->actingAs($admin)->put(route('admin.posts.update', $post), [
            'title' => $post->title, 'category_id' => $post->category_id, 'status' => 'published', 'image_url' => 'https://img.example.com/new.jpg',
        ])->assertRedirect();
        $this->assertSame('https://img.example.com/new.jpg', $post->fresh()->image);
    }
}
