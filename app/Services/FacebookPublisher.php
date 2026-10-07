<?php

namespace App\Services;

use App\Models\Post;
use App\Models\SocialShare;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Posts stories to the ViralDose Facebook Page as a photo (the same news card used for
 * Instagram) with a caption whose article link is clickable. Uses the same Page access
 * token as Instagram – it needs the pages_manage_posts permission.
 */
class FacebookPublisher
{
    public function __construct(private InstagramPublisher $instagram, private ShareCardGenerator $cards) {}

    public function pageId(): string
    {
        return trim((string) setting('facebook_page_id'));
    }

    public function isReady(): bool
    {
        return $this->pageId() !== '' && $this->instagram->token() !== '';
    }

    /**
     * Posting to a Page needs a *Page* access token. If a user token was saved (the error is
     * "publish_actions … deprecated"), exchange it for the Page's own token: GET /{page}?fields=access_token.
     * A Page token from a long-lived user token never expires; cached for a day.
     */
    public function pageToken(): string
    {
        $stored = $this->instagram->token();

        return Cache::remember('facebook.page_token.'.md5($stored.$this->pageId()), 86400, function () use ($stored) {
            try {
                $r = Http::timeout(20)->get(InstagramPublisher::GRAPH.'/'.$this->pageId(), ['fields' => 'access_token', 'access_token' => $stored]);
                $token = (string) $r->json('access_token');

                return $r->successful() && $token !== '' ? $token : $stored;
            } catch (\Throwable) {
                return $stored;
            }
        });
    }

    public function caption(Post $post): string
    {
        $excerpt = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($post->excerpt ?: $post->seoDescription())))), 300);
        $tags = implode(' ', array_slice(preg_split('/\s+/', $this->instagram->hashtags($post), -1, PREG_SPLIT_NO_EMPTY), 0, 8));

        return trim($post->title)."\n\n".($excerpt !== '' ? $excerpt."\n\n" : '').'👉 Read more: '.$post->url().($tags ? "\n\n".$tags : '');
    }

    public function share(Post $post, ?string $caption = null): SocialShare
    {
        $post->loadMissing('category', 'tags');
        $card = $this->cards->generate($post);
        $caption = filled($caption) ? Str::limit(trim($caption), 5000, '') : $this->caption($post);
        $share = SocialShare::create(['post_id' => $post->id, 'network' => 'facebook', 'media_type' => 'image', 'status' => 'processing', 'image' => $card, 'caption' => $caption]);

        try {
            $r = Http::timeout(60)->asForm()->post(InstagramPublisher::GRAPH.'/'.$this->pageId().'/photos', [
                'url' => ShareCardGenerator::url($card),
                'caption' => $caption,
                'published' => 'true',
                'access_token' => $this->pageToken(),
            ]);
            if (! $r->successful()) {
                $msg = $r->json('error.error_user_msg') ?? $r->json('error.message') ?? $r->body();
                if (str_contains($msg, 'publish_actions') || str_contains($msg, 'pages_manage_posts')) {
                    $msg .= ' — The access token needs the pages_manage_posts permission: regenerate it in Graph API Explorer with pages_manage_posts ticked, then save the Viraldose Page token in Settings.';
                    Cache::forget('facebook.page_token.'.md5($this->instagram->token().$this->pageId()));
                }
                throw new RuntimeException('Facebook API: '.$msg);
            }
            $postId = (string) ($r->json('post_id') ?: $r->json('id'));
            $share->update(['status' => 'published', 'external_id' => $postId, 'permalink' => 'https://www.facebook.com/'.$postId, 'response' => 'Published']);
        } catch (\Throwable $e) {
            $share->update(['status' => 'failed', 'response' => Str::limit($e->getMessage(), 2000)]);
        }

        return $share;
    }

    /** Same rules as Instagram auto-share: only once per post, never for noindex/unpublished posts. */
    public function autoShare(Post $post): ?SocialShare
    {
        if (! setting('facebook_auto_share') || ! $this->isReady() || ! $post->isPublished() || $post->noindex) {
            return null;
        }
        $lock = Cache::lock('facebook-autoshare-'.$post->id, 120);
        if (! $lock->get()) {
            return null;
        }
        try {
            $exists = $post->socialShares()->where('network', 'facebook')->whereIn('status', ['processing', 'published'])->exists();

            return $exists ? null : $this->share($post);
        } catch (\Throwable) {
            return null;
        } finally {
            $lock->release();
        }
    }

    /** @return array{name: string|null, followers: int|null} */
    public function page(): array
    {
        $r = Http::timeout(20)->get(InstagramPublisher::GRAPH.'/'.$this->pageId(), ['fields' => 'name,followers_count', 'access_token' => $this->pageToken()]);
        if (! $r->successful()) {
            throw new RuntimeException('Facebook API: '.($r->json('error.message') ?? $r->body()));
        }

        return ['name' => $r->json('name'), 'followers' => $r->json('followers_count')];
    }
}
