<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Services\EmbedRenderer;
use App\Services\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmbedsAndPostTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitizer_keeps_valid_embed_placeholders_and_strips_invalid_ones(): void
    {
        $s = new HtmlSanitizer;
        $ok = $s->clean('<div class="embed" data-provider="twitter" data-url="https://x.com/ViralDose/status/1234567890" contenteditable="false"><span class="embed-label">X</span><span class="embed-url">u</span></div>');
        $this->assertStringContainsString('data-provider="twitter"', $ok);
        $this->assertStringContainsString('data-url="https://x.com/ViralDose/status/1234567890"', $ok);
        $this->assertStringNotContainsString('contenteditable', $ok);

        $bad = $s->clean('<div class="embed" data-provider="youtube" data-url="https://evil.example/watch?v=abcdefghijk">x</div>');
        $this->assertStringNotContainsString('data-url', $bad);
        $bad2 = $s->clean('<div class="embed" data-provider="script" data-url="https://x.com/a/status/1">x</div>');
        $this->assertStringNotContainsString('data-provider', $bad2);
    }

    public function test_embed_renderer_outputs_youtube_twitter_instagram_and_scripts(): void
    {
        $r = new EmbedRenderer;
        $html = $r->render(implode('', [
            '<p>Intro</p>',
            '<div class="embed" data-provider="youtube" data-url="https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10s"><span class="embed-label">y</span></div>',
            '<div class="embed" data-provider="twitter" data-url="https://twitter.com/ViralDose/status/1234567890"></div>',
            '<div class="embed" data-provider="instagram" data-url="https://www.instagram.com/p/Cabc123xyz/?igsh=1"></div>',
            '<div class="embed" data-provider="youtube" data-url="https://evil.example/x"></div>',
            '<p>Outro</p>',
        ]));

        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $html);
        $this->assertStringContainsString('class="twitter-tweet"', $html);
        $this->assertStringContainsString('data-instgrm-permalink="https://www.instagram.com/p/Cabc123xyz/"', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringContainsString('<p>Intro</p>', $html);
        $this->assertStringContainsString('<p>Outro</p>', $html);
        $this->assertStringContainsString('platform.twitter.com/widgets.js', $r->scripts());
        $this->assertStringContainsString('instagram.com/embed.js', $r->scripts());
        $this->assertSame('<p>plain</p>', $r->render('<p>plain</p>'));
    }

    public function test_youtube_id_detection(): void
    {
        foreach (['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ?si=x'] as $url) {
            $this->assertSame('dQw4w9WgXcQ', EmbedRenderer::youtubeId($url), $url);
        }
        $this->assertNull(EmbedRenderer::youtubeId('https://www.youtube.com/channel/abc'));
    }

    public function test_article_page_renders_embeds_from_content(): void
    {
        $post = $this->publishedPost(['content' => '<p>Watch</p><div class="embed" data-provider="youtube" data-url="https://youtu.be/dQw4w9WgXcQ"></div><div class="embed" data-provider="instagram" data-url="https://www.instagram.com/reel/Cabc123xyz/"></div>']);

        $this->get($post->url())->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('instagram-media', false)
            ->assertSee('instagram.com/embed.js', false)
            ->assertDontSee('data-provider=', false);
    }

    public function test_video_post_type_shows_player_poster_and_video_schema(): void
    {
        $post = $this->publishedPost(['post_type' => 'video', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'image' => null]);

        $this->get($post->url())->assertOk()
            ->assertSee('"@type":"VideoObject"', false)
            ->assertSee('"embedUrl":"https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"', false)
            ->assertSee('<meta property="og:type" content="video.other">', false)
            ->assertSee('<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"', false);

        // Listing cards fall back to the YouTube thumbnail and show a play badge.
        $this->get('/')->assertOk()->assertSee('i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', false)->assertSee('M8 5v14l11-7z', false);
    }

    public function test_audio_and_gallery_post_types(): void
    {
        $audio = $this->publishedPost(['post_type' => 'audio', 'audio_url' => 'https://cdn.example.com/episode.mp3']);
        $this->get($audio->url())->assertOk()->assertSee('<audio controls', false)->assertSee('episode.mp3');

        $gallery = Post::factory()->create(['category_id' => $audio->category_id, 'post_type' => 'gallery', 'slug' => 'photos']);
        $gallery->images()->create(['path' => 'https://cdn.example.com/1.jpg', 'caption' => 'One']);
        $gallery->images()->create(['path' => 'https://cdn.example.com/2.jpg']);
        $this->get($gallery->fresh()->url())->assertOk()->assertSee('1/2 · One')->assertSee('cdn.example.com/2.jpg');
    }

    public function test_admin_can_save_video_post_and_type_is_validated(): void
    {
        $admin = $this->admin();
        $category = Category::factory()->create(['slug' => 'news']);

        $this->actingAs($admin)->post('/admin/posts', ['title' => 'Clip', 'category_id' => $category->id, 'status' => 'published', 'post_type' => 'video'])
            ->assertSessionHasErrors('video_url');

        $this->actingAs($admin)->post('/admin/posts', ['title' => 'Clip', 'category_id' => $category->id, 'status' => 'published', 'post_type' => 'video', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'content' => '<p>Hi</p><div class="embed" data-provider="twitter" data-url="https://x.com/a/status/99"></div>'])->assertRedirect();
        $post = Post::first();
        $this->assertSame('video', $post->post_type);
        $this->assertTrue($post->isVideo());
        $this->assertStringContainsString('data-provider="twitter"', $post->content);
        $this->actingAs($admin)->get(route('admin.posts.edit', $post))->assertOk()->assertSee('ql-instagram', false)->assertSee('Post Type');
    }
}
