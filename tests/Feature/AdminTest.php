<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\Comment;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Subscriber;
use App\Models\User;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_requires_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/posts')->assertRedirect('/admin/login');
    }

    public function test_login_and_logout(): void
    {
        $user = $this->admin();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);

        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_inactive_users_cannot_log_in(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_dashboard_renders(): void
    {
        $this->publishedPost();
        $this->actingAs($this->admin())->get('/admin')->assertOk()->assertSee('Dashboard');
    }

    public function test_every_admin_screen_renders_with_realistic_data(): void
    {
        $this->seed(DemoContentSeeder::class);
        $admin = User::where('role', 'admin')->first();
        $post = Post::first();
        $post->delete(); // one trashed post for the trash view
        Ad::create(['name' => 'Header', 'slot' => 'header', 'code' => '<div>ad</div>', 'is_active' => true]);
        Redirect::create(['from_path' => '/old', 'to_path' => '/new']);
        Subscriber::create(['email' => 's@example.com', 'token' => 'abc']);
        $message = ContactMessage::create(['name' => 'A', 'email' => 'a@example.com', 'message' => 'Hello there friend']);
        $page = Page::first();
        $category = Category::first();
        $live = Post::published()->first();

        $urls = [
            '/admin', '/admin/posts', '/admin/posts?status=scheduled', '/admin/posts?trashed=1', '/admin/posts/create',
            "/admin/posts/{$live->id}/edit", '/admin/categories', '/admin/categories/create', "/admin/categories/{$category->id}/edit",
            '/admin/tags', '/admin/pages', '/admin/pages/create', "/admin/pages/{$page->id}/edit",
            '/admin/comments', '/admin/comments?status=approved', '/admin/users', '/admin/users/create', "/admin/users/{$admin->id}/edit",
            '/admin/ads', '/admin/ads/create', '/admin/redirects', '/admin/settings', '/admin/subscribers',
            '/admin/messages', "/admin/messages/{$message->id}", '/admin/profile',
        ];
        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        // And the public site with the same data set.
        foreach (['/', '/category/'.$category->slug, $live->url(), '/search?q=a', '/contact', '/feed', '/sitemap.xml', '/sitemap-posts-1.xml', '/news-sitemap.xml'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_can_create_post_with_image_tags_and_sanitised_content(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $category = Category::factory()->create(['slug' => 'tech']);

        $response = $this->actingAs($admin)->post('/admin/posts', [
            'title' => 'New Phone Launched Today',
            'category_id' => $category->id,
            'content' => '<p>Hello <script>alert(1)</script><a href="javascript:alert(1)">x</a> <a href="https://example.com">ext</a></p><img src="https://example.com/a.jpg" onerror="alert(1)">',
            'status' => 'published',
            'tags' => 'Mobiles, Launch, mobiles',
            'image' => UploadedFile::fake()->image('phone.jpg', 1400, 900),
            'image_alt' => 'The new phone',
            'meta_description' => 'A phone was launched today.',
            'is_featured' => 1,
            'allow_comments' => 1,
        ]);

        $post = Post::first();
        $response->assertRedirect(route('admin.posts.edit', $post));
        $this->assertSame('new-phone-launched-today', $post->slug);
        $this->assertTrue($post->is_featured);
        $this->assertCount(2, $post->tags);
        $this->assertStringNotContainsString('<script', $post->content);
        $this->assertStringNotContainsString('javascript:', $post->content);
        $this->assertStringNotContainsString('onerror', $post->content);
        $this->assertStringContainsString('rel="noopener nofollow"', $post->content);
        $this->assertNotNull($post->image);
        Storage::disk('public')->assertExists($post->image);
        $info = pathinfo($post->image);
        Storage::disk('public')->assertExists($info['dirname'].'/'.$info['filename'].'-medium.webp');

        $this->get('/tech/new-phone-launched-today')->assertOk()->assertSee('srcset=', false)->assertSee('alt="The new phone"', false);
    }

    public function test_slug_collisions_are_made_unique(): void
    {
        $category = Category::factory()->create();
        Post::factory()->create(['category_id' => $category->id, 'title' => 'Same Title', 'slug' => 'same-title']);
        $second = Post::factory()->create(['category_id' => $category->id, 'title' => 'Same Title', 'slug' => null]);

        $this->assertSame('same-title-2', $second->slug);
    }

    public function test_author_can_only_edit_own_posts_and_cannot_feature(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $category = Category::factory()->create();
        $mine = Post::factory()->create(['user_id' => $author->id, 'category_id' => $category->id]);
        $theirs = Post::factory()->create(['user_id' => $other->id, 'category_id' => $category->id]);

        $this->actingAs($author)->get(route('admin.posts.edit', $theirs))->assertForbidden();
        $this->actingAs($author)->get(route('admin.posts.edit', $mine))->assertOk();
        $this->actingAs($author)->put(route('admin.posts.update', $mine), [
            'title' => 'Updated by author', 'category_id' => $category->id, 'status' => 'published', 'is_slider' => 1, 'is_featured' => 1,
        ])->assertRedirect();
        $mine->refresh();
        $this->assertSame('Updated by author', $mine->title);
        $this->assertFalse($mine->is_featured);

        $this->actingAs($author)->get('/admin/categories')->assertForbidden();
        $this->actingAs($author)->get('/admin/settings')->assertForbidden();
    }

    public function test_editor_cannot_access_admin_only_areas(): void
    {
        $editor = User::factory()->editor()->create();
        $this->actingAs($editor)->get('/admin/categories')->assertOk();
        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
        $this->actingAs($editor)->get('/admin/settings')->assertForbidden();
    }

    public function test_category_and_page_crud(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/categories', ['name' => 'Cricket News', 'color' => '#16a34a', 'show_in_menu' => 1, 'is_active' => 1])->assertRedirect('/admin/categories');
        $this->assertDatabaseHas('categories', ['slug' => 'cricket-news']);

        $this->actingAs($admin)->post('/admin/pages', ['title' => 'About', 'content' => '<p>Hi</p>', 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('pages', ['slug' => 'about']);
        $this->get('/page/about')->assertOk();
    }

    public function test_comment_moderation_and_settings_update(): void
    {
        $admin = $this->admin();
        $post = $this->publishedPost();
        $comment = Comment::factory()->create(['post_id' => $post->id, 'status' => 'pending', 'body' => 'Nice one']);

        $this->actingAs($admin)->get('/admin/comments')->assertOk()->assertSee('Nice one');
        $this->actingAs($admin)->put(route('admin.comments.update', $comment), ['status' => 'approved'])->assertRedirect();
        $this->get($post->url())->assertSee('Nice one');

        $this->actingAs($admin)->put('/admin/settings', [
            'site_name' => 'ViralDose Test', 'posts_per_page' => 10, 'post_url_format' => 'flat',
            'organization_type' => 'NewsMediaOrganization', 'language' => 'en',
        ])->assertRedirect();
        $this->assertSame('ViralDose Test', site_name());

        // Flat URL format: canonical URL flips and the category form redirects.
        $this->get('/india-wins-the-final')->assertOk();
        $this->get('/sports/india-wins-the-final')->assertRedirect('http://localhost/india-wins-the-final');
    }

    public function test_redirect_management_and_csv_import(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/redirects', ['from_path' => '/Old-Page/', 'to_path' => '/new-page', 'status_code' => 301])->assertRedirect();
        $this->assertDatabaseHas('redirects', ['from_path' => '/old-page', 'to_path' => '/new-page']);

        $csv = UploadedFile::fake()->createWithContent('r.csv', "from,to\n/a,/b\n/c,/d,302\n");
        $this->actingAs($admin)->post('/admin/redirects/import', ['file' => $csv])->assertRedirect();
        $this->assertDatabaseHas('redirects', ['from_path' => '/c', 'status_code' => 302]);
    }
}
