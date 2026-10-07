<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Reel;
use App\Models\SocialShare;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Post tags + category as hashtags (#HyderabadMetro), then the default hashtags from Settings.
     * Deduplicated case-insensitively and capped at Instagram's limit of 30.
     */
    public function hashtags(Post|Reel $subject): string
    {
        $post = $subject instanceof Post ? $subject : $subject->post;
        $words = [];
        if ($post) {
            $post->loadMissing('tags', 'category');
            $words = $post->tags->pluck('name')->all();
            if ($post->category) {
                $words[] = $post->category->name;
            }
        }
        $tags = collect($words)
            ->map(fn ($w) => '#'.implode('', array_map(fn ($part) => Str::ucfirst($part), preg_split('/[^\p{L}\p{N}]+/u', (string) $w, -1, PREG_SPLIT_NO_EMPTY))))
            ->merge(preg_split('/\s+/', trim((string) setting('instagram_hashtags')), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($t) => mb_strlen($t) > 2 && ! preg_match('/^#\d+$/', $t))
            ->map(fn ($t) => str_starts_with($t, '#') ? $t : '#'.$t)
            ->unique(fn ($t) => mb_strtolower($t))
            ->take(30);

        return $tags->implode(' ');
    }

    public function caption(Post|Reel $subject, ?string $override = null): string
    {
        if ($override !== null && trim($override) !== '') {
            return Str::limit(trim($override), 2200, '');
        }
        $template = (string) setting('instagram_caption_template');
        $caption = strtr($template, [
            '{title}' => $subject->title,
            '{excerpt}' => (string) ($subject instanceof Post ? $subject->excerpt : $subject->caption),
            '{category}' => (string) $subject->category?->name,
            '{url}' => $subject->url(),
            '{hashtags}' => $this->hashtags($subject),
            '\n' => "\n",
        ]);

        return Str::limit(trim($caption), 2200, '');
    }

    /** Seconds to wait between "is the image container ready?" checks (0 in tests). */
    public int $pollDelay = 2;

    /**
     * Auto-share a freshly published post when "Auto-share" is on. Safe to call from every
     * publish path (editor, bulk publish, content agent, scheduled posts via cron): it skips
     * posts that already have a pending/published Instagram share and uses a lock against races.
     */
    public function autoShare(Post $post): ?SocialShare
    {
        if (! setting('instagram_auto_share') || ! $this->isReady() || ! $post->isPublished() || $post->noindex) {
            return null;
        }
        $lock = Cache::lock('instagram-autoshare-'.$post->id, 120);
        if (! $lock->get()) {
            return null;
        }
        try {
            $exists = $post->socialShares()->where('network', 'instagram')->whereIn('status', ['processing', 'published'])->exists();

            return $exists ? null : $this->shareImage($post->loadMissing('category', 'tags'));
        } catch (\Throwable) {
            return null;
        } finally {
            $lock->release();
        }
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

        return $this->publishImage($share, $imageUrl, $caption);
    }

    /**
     * Photo reel → Instagram feed photo. Instagram only accepts JPEG within 4:5…1.91:1,
     * so the stored image is re-rendered as a 1080×1350 JPEG first.
     */
    public function shareReelPhoto(Reel $reel, ?string $caption = null): SocialShare
    {
        $caption = $this->caption($reel, $caption);
        $share = SocialShare::create(['post_id' => $reel->post_id, 'reel_id' => $reel->id, 'network' => 'instagram', 'media_type' => 'image', 'status' => 'processing', 'caption' => $caption]);
        try {
            $card = $this->cards->photoCard((string) $reel->thumbnail, 'reel-'.$reel->id);
        } catch (\Throwable $e) {
            $share->update(['status' => 'failed', 'response' => Str::limit($e->getMessage(), 2000)]);

            return $share;
        }
        $share->update(['image' => $card]);

        return $this->publishImage($share, ShareCardGenerator::url($card), $caption);
    }

    /**
     * Video reel (uploaded file or direct .mp4 URL) → Instagram Reel.
     */
    public function shareReelVideo(Reel $reel, ?string $caption = null): SocialShare
    {
        $caption = $this->caption($reel, $caption);
        $cover = null;
        if ($reel->thumbnail) {
            try {
                $cover = ShareCardGenerator::url($this->cards->photoCard($reel->thumbnail, 'reel-'.$reel->id.'-cover'));
            } catch (\Throwable) {
            }
        }
        $share = SocialShare::create(['post_id' => $reel->post_id, 'reel_id' => $reel->id, 'network' => 'instagram', 'media_type' => 'reel', 'status' => 'processing', 'image' => $cover, 'caption' => $caption]);
        try {
            $creationId = $this->createContainer(array_filter([
                'media_type' => 'REELS', 'video_url' => $reel->videoUrl(), 'caption' => $caption, 'cover_url' => $cover, 'share_to_feed' => 'true',
            ]));
            $share->update(['creation_id' => $creationId, 'response' => 'Uploading to Instagram… status is checked every minute.']);
        } catch (\Throwable $e) {
            $share->update(['status' => 'failed', 'response' => Str::limit($e->getMessage(), 2000)]);
        }

        return $share;
    }

    private function publishImage(SocialShare $share, string $imageUrl, string $caption): SocialShare
    {

        try {
            $creationId = $this->createContainer(['image_url' => $imageUrl, 'caption' => $caption]);
            $share->update(['creation_id' => $creationId]);
            // Image containers usually finish within a few seconds; wait briefly instead of
            // leaving the share "processing" until the cron job (or a manual check) picks it up.
            for ($i = 0; $i < 6; $i++) {
                if ($this->publishContainer($share)) {
                    break;
                }
                if ($this->pollDelay > 0) {
                    sleep($this->pollDelay);
                }
            }
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
