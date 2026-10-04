<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\ContactMessage;
use App\Models\Post;
use App\Models\PostView;
use App\Models\Subscriber;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $own = fn ($q) => $user->canManageAllPosts() ? $q : $q->where('user_id', $user->id);

        $stats = [
            'published' => $own(Post::query())->published()->count(),
            'drafts' => $own(Post::query())->where('status', Post::STATUS_DRAFT)->count(),
            'scheduled' => $own(Post::query())->where('status', Post::STATUS_PUBLISHED)->where('published_at', '>', now())->count(),
            'views_today' => (int) PostView::where('viewed_on', now()->toDateString())->sum('count'),
            'views_week' => (int) PostView::where('viewed_on', '>=', now()->subDays(6)->toDateString())->sum('count'),
            'pending_comments' => Comment::pending()->count(),
            'subscribers' => Subscriber::where('is_active', true)->count(),
            'unread_messages' => ContactMessage::where('is_read', false)->count(),
        ];

        $recentPosts = $own(Post::query())->with(['category:id,name,slug', 'author:id,name'])->latest('updated_at')->limit(10)->get();
        $topPosts = Post::published()->forListing()->orderByDesc('views')->limit(5)->get();
        $trending = Post::trending(7, 5);
        $pendingComments = $user->canManageAllPosts() ? Comment::pending()->with('post:id,title,slug,category_id')->latest()->limit(5)->get() : collect();

        return view('admin.dashboard', compact('stats', 'recentPosts', 'topPosts', 'trending', 'pendingComments'));
    }
}
