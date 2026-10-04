@extends('layouts.admin')
@section('title', 'Dashboard')
@section('actions')<a href="{{ route('admin.posts.create') }}" class="btn-primary !px-3 !py-1.5 text-xs"><x-admin.icon name="plus" class="h-4 w-4" /> Add Post</a>@endsection
@section('content')
@php $me = auth()->user(); @endphp
{{-- Stat tiles --}}
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['Published posts', number_format($stats['published']), route('admin.posts.index', ['status' => 'published']), 'posts', 'bg-blue-600'],
        ['Views today', number_format($stats['views_today']), null, 'chart', 'bg-green-600'],
        ['Views (7 days)', number_format($stats['views_week']), null, 'eye', 'bg-teal-600'],
        ['Drafts / scheduled', $stats['drafts'].' / '.$stats['scheduled'], route('admin.posts.index', ['status' => 'draft']), 'page', 'bg-yellow-500'],
        ['Pending comments', $stats['pending_comments'], $me->canManageAllPosts() ? route('admin.comments.index') : null, 'comments', 'bg-orange-500'],
        ['Subscribers', number_format($stats['subscribers']), $me->isAdmin() ? route('admin.subscribers.index') : null, 'mail', 'bg-purple-600'],
        ['Unread messages', $stats['unread_messages'], $me->isAdmin() ? route('admin.messages.index') : null, 'inbox', 'bg-pink-600'],
        ['Not indexed by Google', $indexing['not_indexed'], $me->canManageAllPosts() ? route('admin.google.index', ['tab' => 'indexing']) : null, 'google', 'bg-red-600'],
    ] as [$label, $value, $link, $icon, $bg])
        <a href="{{ $link ?: '#' }}" class="card flex items-center gap-4 p-4 {{ $link ? 'hover:border-brand-600' : 'pointer-events-none' }}">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-white {{ $bg }}"><x-admin.icon :name="$icon" class="h-5 w-5" /></span>
            <span><span class="block text-2xl font-black leading-tight">{{ $value }}</span><span class="text-xs font-semibold uppercase tracking-wide text-ink-500">{{ $label }}</span></span>
        </a>
    @endforeach
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    {{-- Site views chart --}}
    <section class="card p-5 xl:col-span-2">
        <div class="mb-2 flex items-center justify-between"><h2 class="font-bold">Page views · last 30 days</h2><span class="text-xs text-ink-500">on-site counter</span></div>
        <x-admin.bar-chart :series="$viewSeries" label="views" />
    </section>

    {{-- Google summary --}}
    <section class="card p-5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="flex items-center gap-2 font-bold"><x-admin.icon name="google" class="h-5 w-5" /> Google · 28 days</h2>
            @if($me->canManageAllPosts())<a href="{{ route('admin.google.index') }}" class="text-xs font-semibold text-brand-600">Open →</a>@endif
        </div>
        @if(! $googleConfigured)
            <p class="text-sm text-ink-700">Connect Search Console, the Indexing API and GA4 with one service-account key to see clicks, impressions, index status and traffic here.</p>
            @if($me->isAdmin())<a href="{{ route('admin.settings.edit', ['tab' => 'google']) }}" class="btn-primary mt-3">Connect Google</a>@endif
        @else
            @if($sc)
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs uppercase text-ink-500">Clicks</dt><dd class="text-xl font-black">{{ number_format($sc['totals']['clicks']) }}</dd></div>
                    <div><dt class="text-xs uppercase text-ink-500">Impressions</dt><dd class="text-xl font-black">{{ number_format($sc['totals']['impressions']) }}</dd></div>
                    <div><dt class="text-xs uppercase text-ink-500">CTR</dt><dd class="text-xl font-black">{{ $sc['totals']['ctr'] }}%</dd></div>
                    <div><dt class="text-xs uppercase text-ink-500">Avg. position</dt><dd class="text-xl font-black">{{ $sc['totals']['position'] }}</dd></div>
                </dl>
            @elseif(isset($googleErrors['search_console']))
                <p class="rounded bg-red-50 px-3 py-2 text-xs text-red-700">Search Console: {{ $googleErrors['search_console'] }}</p>
            @endif
            @if($ga)
                <dl class="mt-4 grid grid-cols-3 gap-3 border-t border-ink-100 pt-3 text-sm">
                    <div><dt class="text-xs uppercase text-ink-500">Users</dt><dd class="text-lg font-black">{{ number_format($ga['totals']['users']) }}</dd></div>
                    <div><dt class="text-xs uppercase text-ink-500">Sessions</dt><dd class="text-lg font-black">{{ number_format($ga['totals']['sessions']) }}</dd></div>
                    <div><dt class="text-xs uppercase text-ink-500">Pageviews</dt><dd class="text-lg font-black">{{ number_format($ga['totals']['pageviews']) }}</dd></div>
                </dl>
            @elseif(isset($googleErrors['analytics']))
                <p class="mt-3 rounded bg-red-50 px-3 py-2 text-xs text-red-700">Analytics: {{ $googleErrors['analytics'] }}</p>
            @endif
            <div class="mt-4 border-t border-ink-100 pt-3 text-xs text-ink-700">
                <p><strong>{{ $indexing['submitted_today'] }}</strong> URLs submitted to search engines today · <strong class="{{ $indexing['errors_week'] ? 'text-red-600' : '' }}">{{ $indexing['errors_week'] }}</strong> errors this week</p>
            </div>
        @endif
    </section>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-2 overflow-x-auto">
        <h2 class="border-b border-ink-100 px-5 py-4 font-bold">Recently edited</h2>
        <table class="table-admin">
            <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Google</th><th>Updated</th></tr></thead>
            <tbody>
            @forelse($recentPosts as $post)
                <tr>
                    <td><a href="{{ route('admin.posts.edit', $post) }}" class="font-medium hover:text-brand-600">{{ $post->title }}</a><span class="block text-xs text-ink-500">{{ $post->author?->name }}</span></td>
                    <td>{{ $post->category?->name }}</td>
                    <td>@include('admin.posts._status', ['post' => $post])</td>
                    <td>@include('admin.posts._index_status', ['post' => $post])</td>
                    <td class="whitespace-nowrap text-ink-500">{{ $post->updated_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-8 text-center text-ink-500">No posts yet. <a href="{{ route('admin.posts.create') }}" class="text-brand-600 underline">Write your first story</a>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="space-y-6">
        <div class="card">
            <h2 class="border-b border-ink-100 px-5 py-4 font-bold">Trending this week</h2>
            <ol class="divide-y divide-ink-100">
                @forelse($trending as $post)<li class="px-5 py-3 text-sm"><a href="{{ $post->url() }}" target="_blank" class="hover:text-brand-600">{{ $post->title }}</a><span class="block text-xs text-ink-500">{{ number_format($post->views) }} total views</span></li>
                @empty<li class="px-5 py-6 text-sm text-ink-500">No traffic data yet.</li>@endforelse
            </ol>
        </div>
        @if($indexing['recent']->isNotEmpty())
        <div class="card">
            <h2 class="border-b border-ink-100 px-5 py-4 font-bold">Indexing activity</h2>
            <ul class="divide-y divide-ink-100 text-xs">
                @foreach($indexing['recent'] as $log)
                    <li class="flex items-start gap-2 px-5 py-2">
                        <span class="{{ $log->status === 'ok' ? 'text-green-600' : 'text-red-600' }}"><x-admin.icon :name="$log->status === 'ok' ? 'check' : 'x'" class="h-4 w-4" /></span>
                        <span class="min-w-0 flex-1"><span class="font-semibold">{{ str_replace('_', ' ', $log->provider) }}</span> · {{ $log->action }} · <span class="truncate">{{ $log->post?->title ?? $log->url }}</span><span class="block text-ink-500">{{ $log->created_at->diffForHumans() }}</span></span>
                    </li>
                @endforeach
            </ul>
        </div>
        @endif
        @if($pendingComments->isNotEmpty())
        <div class="card">
            <h2 class="border-b border-ink-100 px-5 py-4 font-bold">Awaiting moderation</h2>
            <ul class="divide-y divide-ink-100">
                @foreach($pendingComments as $comment)
                    <li class="px-5 py-3 text-sm"><span class="font-semibold">{{ $comment->name }}</span> on <em>{{ $comment->post?->title }}</em><p class="mt-1 text-ink-700 line-clamp-2">{{ $comment->body }}</p></li>
                @endforeach
            </ul>
            <a href="{{ route('admin.comments.index') }}" class="block px-5 py-3 text-sm font-semibold text-brand-600">Moderate →</a>
        </div>
        @endif
    </div>
</div>
@endsection
