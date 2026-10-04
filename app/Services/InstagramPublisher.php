<?php

namespace App\Services;

use App\Models\Post;
use App\Models\SocialShare;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Instagram Graph API content publishing for a Business/Creator account:
 *   1) POST /{ig-user-id}/media         -> container (creation_id)
 *   2) POST /{ig-user-id}/media_publish -> media id
 * Images publish immediately; reels need processing and are finished by the
 * `social:process` scheduler (or the "Check status" button).
 */
class InstagramPublisher
{
    public const GRAPH = 'https://graph.facebook.com/v21.0';

    public function __construct(private ShareCardGenerator $cards) {}

    public function isReady(): bool
    {
        return setting('instagram_business_id') && $this->token() !== '';
    }

    public function token(): string
    {
        $raw = (string) setting('instagram_access_token');
        if ($raw === '') {
            return '';
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return $raw;
        }
    }

    /**
     * @return array{username: string, followers: int|null, picture: string|null}
     */
    public function account(): array
    {
        $r = Http::timeout(20)->get(self::GRAPH.'/'.setting('instagram_business_id'), [
            'fields' => 'username,followers_count,profile_picture_url',
            'access_token' => $this->token(),
        ]);
        $this->guard($r);

        return ['username' => $r->json('username'), 'followers' => $r->json('followers_count'), 'picture' => $r->json('profile_picture_url')];
    }

    public function caption(Post $post, ?string $override = null): string
    {
        if ($override !== null && trim($override) !== '') {
            return Str::limit(trim($override), 2200, '');
        }
        $template = (string) setting('instagram_caption_template');
        $caption = strtr($template, [
            '{title}' => $post->title,
            '{excerpt}' => (string) $post->excerpt,
            '{category}' => (string) $post->category?->name,
            '{url}' => $post->url(),
            '{hashtags}' => (string) setting('instagram_hashtags'),
            '\n' => "\n",
        ]);

        return Str::limit(trim($caption), 2200, '');
    }

    /**
     * One click: build the card (or use the given image URL) and publish it.
     */
    public function shareImage(Post $post, ?string $caption = null, ?string $imageUrl = null): SocialShare
    {
        $cardFile = null;
        if (! $imageUrl) {
            $cardFile = $this->cards->generate($post);
            $imageUrl = ShareCardGenerator::url($cardFile);
        }
        $caption = $this->caption($post, $caption);
        $share = SocialShare::create(['post_id' => $post->id, 'network' => 'instagram', 'media_type' => 'image', 'status' => 'processing', 'image' => $cardFile ?: $imageUrl, 'caption' => $caption]);

        try {
            $creationId = $this->createContainer(['image_url' => $imageUrl, 'caption' => $caption]);
            $share->update(['creation_id' => $creationId]);
            $this->publishContainer($share);
        } catch (\Throwable $e) {
            $share->update(['status' => 'failed', 'response' => Str::limit($e->getMessage(), 2000)]);
        }

        return $share;
    }

    public function shareReel(Post $post, string $videoUrl, ?string $caption = null, ?string $coverUrl = null): SocialShare
    {
        $caption = $this->caption($post, $caption);
        $share = SocialShare::create(['post_id' => $post->id, 'network' => 'instagram', 'media_type' => 'reel', 'status' => 'processing', 'image' => $coverUrl, 'caption' => $caption]);
        try {
            $creationId = $this->createContainer(array_filter([
                'media_type' => 'REELS', 'video_url' => $videoUrl, 'caption' => $caption, 'cover_url' => $coverUrl, 'share_to_feed' => 'true',
            ]));
            $share->update(['creation_id' => $creationId, 'response' => 'Uploading to Instagram… status is checked every minute.']);
        } catch (\Throwable $e) {
            $share->update(['status' => 'failed', 'response' => Str::limit($e->getMessage(), 2000)]);
        }

        return $share;
    }

    /**
     * Publishes a container once Instagram reports it FINISHED. Returns true when done (either way).
     */
    public function publishContainer(SocialShare $share): bool
    {
        if (! $share->creation_id) {
            return true;
        }
        $status = Http::timeout(20)->get(self::GRAPH.'/'.$share->creation_id, ['fields' => 'status_code,status', 'access_token' => $this->token()]);
        $this->guard($status);
        $code = $status->json('status_code');
        if ($code === 'ERROR' || $code === 'EXPIRED') {
            $share->update(['status' => 'failed', 'response' => 'Instagram could not process the media: '.($status->json('status') ?? $code)]);

            return true;
        }
        if ($code !== 'FINISHED' && $code !== null) {
            $share->update(['response' => 'Processing ('.$code.')…']);

            return false;
        }
        $r = Http::timeout(30)->asForm()->post(self::GRAPH.'/'.setting('instagram_business_id').'/media_publish', [
            'creation_id' => $share->creation_id,
            'access_token' => $this->token(),
        ]);
        $this->guard($r);
        $mediaId = (string) $r->json('id');
        $permalink = null;
        try {
            $info = Http::timeout(20)->get(self::GRAPH.'/'.$mediaId, ['fields' => 'permalink', 'access_token' => $this->token()]);
            $permalink = $info->json('permalink');
        } catch (\Throwable) {
        }
        $share->update(['status' => 'published', 'external_id' => $mediaId, 'permalink' => $permalink, 'response' => 'Published']);

        return true;
    }

    private function createContainer(array $params): string
    {
        $r = Http::timeout(30)->asForm()->post(self::GRAPH.'/'.setting('instagram_business_id').'/media', $params + ['access_token' => $this->token()]);
        $this->guard($r);
        $id = $r->json('id');
        if (! $id) {
            throw new RuntimeException('Instagram did not return a container id.');
        }

        return (string) $id;
    }

    private function guard($response): void
    {
        if ($response->successful()) {
            return;
        }
        $msg = $response->json('error.error_user_msg') ?? $response->json('error.message') ?? $response->body();
        throw new RuntimeException('Instagram API: '.$msg, $response->status());
    }
}
