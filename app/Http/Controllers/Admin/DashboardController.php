<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\ContactMessage;
use App\Models\IndexingLog;
use App\Models\Post;
use App\Models\PostView;
use App\Models\Subscriber;
use App\Services\Google\Analytics;
use App\Services\Google\GoogleClient;
use App\Services\Google\SearchConsole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GoogleClient $google, SearchConsole $searchConsole, Analytics $analytics)
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

        // Internal page views, last 30 days (fills missing days with 0).
        $raw = DB::table('post_views')->where('viewed_on', '>=', now()->subDays(29)->toDateString())
            ->select('viewed_on', DB::raw('SUM(count) as total'))->groupBy('viewed_on')->pluck('total', 'viewed_on')
            ->mapWithKeys(fn ($total, $day) => [substr((string) $day, 0, 10) => (int) $total]);
        $viewSeries = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $viewSeries[] = ['date' => $day, 'value' => (int) ($raw[$day] ?? 0)];
        }

        $recentPosts = $own(Post::query())->with(['category:id,name,slug', 'author:id,name'])->latest('updated_at')->limit(8)->get();
        $topPosts = Post::published()->forListing()->orderByDesc('views')->limit(5)->get();
        $trending = Post::trending(7, 5);
        $pendingComments = $user->canManageAllPosts() ? Comment::pending()->with('post:id,title,slug,category_id')->latest()->limit(5)->get() : collect();

        $googleConfigured = $google->isConfigured();
        $sc = $ga = null;
        $googleErrors = [];
        if ($googleConfigured && $user->canManageAllPosts()) {
            try {
                $sc = $searchConsole->overview(28);
            } catch (\Throwable $e) {
                $googleErrors['search_console'] = $e->getMessage();
            }
            if ($analytics->isReady()) {
                try {
                    $ga = $analytics->overview(28);
                } catch (\Throwable $e) {
                    $googleErrors['analytics'] = $e->getMessage();
                }
            }
        }
        $indexing = [
            'submitted_today' => IndexingLog::where('status', 'ok')->whereIn('provider', ['google_indexing', 'indexnow'])->where('created_at', '>=', now()->startOfDay())->count(),
            'errors_week' => IndexingLog::where('status', 'error')->where('created_at', '>=', now()->subDays(7))->count(),
            'not_indexed' => Post::published()->whereIn('index_status', ['FAIL', 'NEUTRAL'])->count(),
            'recent' => IndexingLog::with('post:id,title')->latest('created_at')->limit(6)->get(),
        ];

        return view('admin.dashboard', compact('stats', 'viewSeries', 'recentPosts', 'topPosts', 'trending', 'pendingComments', 'googleConfigured', 'sc', 'ga', 'googleErrors', 'indexing'));
    }
}
