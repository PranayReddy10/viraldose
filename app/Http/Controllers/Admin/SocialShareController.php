<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Reel;
use App\Models\SocialShare;
use App\Services\InstagramPublisher;
use App\Services\ShareCardGenerator;
use Illuminate\Http\Request;

class SocialShareController extends Controller
{
    public function __construct(private InstagramPublisher $instagram, private ShareCardGenerator $cards) {}

    /**
     * One click: generate the news card and publish it to the Instagram page.
     */
    public function instagram(Request $request, Post $post)
    {
        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:2200'],
            'media' => ['nullable', 'in:card,image,reel'],
        ]);
        abort_unless($request->user()->canManageAllPosts() || $post->user_id === $request->user()->id, 403);

        if (! $this->instagram->isReady()) {
            return back()->withErrors(['instagram' => 'Connect the Instagram account first (Settings → Instagram).']);
        }
        $media = $data['media'] ?? 'card';
        if ($media === 'reel') {
            $video = $post->video();
            if (! $video || $video['type'] !== 'file') {
                return back()->withErrors(['instagram' => 'Reels need a direct .mp4 video URL on the post (YouTube links cannot be re-posted).']);
            }
            $share = $this->instagram->shareReel($post, $video['src'], $data['caption'] ?? null, $post->imageUrl('large'));
        } elseif ($media === 'image' && $post->image) {
            // Instagram accepts only JPEG in 4:5…1.91:1 – re-render the featured image as a photo card.
            try {
                $photo = $this->cards->photoCard($post->image, 'post-'.$post->id.'-photo');
            } catch (\Throwable $e) {
                return back()->withErrors(['instagram' => 'The featured image could not be prepared: '.$e->getMessage()]);
            }
            $share = $this->instagram->shareImage($post, $data['caption'] ?? null, ShareCardGenerator::url($photo));
        } else {
            $share = $this->instagram->shareImage($post, $data['caption'] ?? null);
        }

        return match ($share->status) {
            'published' => back()->with('status', 'Posted to Instagram'.($share->permalink ? ': '.$share->permalink : '.')),
            'processing' => back()->with('status', 'Sent to Instagram – it is processing the video and will publish automatically within a few minutes.'),
            default => back()->withErrors(['instagram' => 'Instagram rejected the post: '.$share->response]),
        };
    }

    /**
     * Downloads the generated 1080×1350 card so it can be posted manually.
     */
    public function card(Request $request, Post $post)
    {
        try {
            $file = $this->cards->generate($post, force: $request->boolean('refresh'));
            $bytes = $this->cards->bytes($file);
        } catch (\Throwable $e) {
            report($e);
            abort(500, 'Card could not be generated: '.$e->getMessage());
        }
        $name = 'instagram-'.$post->slug.'.jpg';
        $disposition = $request->boolean('preview') ? 'inline' : 'attachment';

        // Streamed directly (not redirected to storage) so the preview works on any host / disk.
        return response($bytes, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => $disposition.'; filename="'.$name.'"',
            'Cache-Control' => $request->boolean('preview') ? 'private, max-age=300' : 'no-store',
        ]);
    }

    /**
     * Post a reel to Instagram: photo reels as a feed photo, video reels as a Reel.
     */
    public function reel(Request $request, Reel $reel)
    {
        $data = $request->validate(['caption' => ['nullable', 'string', 'max:2200']]);
        if (! $this->instagram->isReady()) {
            return back()->withErrors(['instagram' => 'Connect the Instagram account first (Settings → Instagram).']);
        }
        if ($reel->isImage()) {
            if (! $reel->thumbnail) {
                return back()->withErrors(['instagram' => 'Upload the photo first.']);
            }
            $share = $this->instagram->shareReelPhoto($reel, $data['caption'] ?? null);
        } elseif ($reel->videoUrl()) {
            $share = $this->instagram->shareReelVideo($reel, $data['caption'] ?? null);
        } else {
            return back()->withErrors(['instagram' => 'Only uploaded videos, direct .mp4 links and photos can be posted (YouTube / Instagram embeds cannot be re-posted).']);
        }

        return match ($share->status) {
            'published' => back()->with('status', 'Posted to Instagram'.($share->permalink ? ': '.$share->permalink : '.')),
            'processing' => back()->with('status', 'Sent to Instagram – it is processing the video and will publish automatically within a few minutes.'),
            default => back()->withErrors(['instagram' => 'Instagram rejected the post: '.$share->response]),
        };
    }

    public function check(SocialShare $share)
    {
        try {
            $this->instagram->publishContainer($share);
        } catch (\Throwable $e) {
            $share->update(['status' => 'failed', 'response' => $e->getMessage()]);
        }

        return back()->with('status', 'Instagram status: '.$share->fresh()->status);
    }

    public function destroy(SocialShare $share)
    {
        $share->delete();

        return back()->with('status', 'Share record removed.');
    }
}
