<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\SocialShare;
use App\Services\ImageService;
use App\Services\InstagramPublisher;
use App\Services\ShareCardGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            $share = $this->instagram->shareImage($post, $data['caption'] ?? null, $post->imageUrl('large'));
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
        $file = $this->cards->generate($post, force: $request->boolean('refresh'));
        [$disk, $path] = ImageService::resolve($file);
        $name = 'instagram-'.$post->slug.'.jpg';

        if ($request->boolean('preview')) {
            return redirect(ImageService::publicUrl($file));
        }

        return Storage::disk($disk)->download($path, $name);
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
