@extends('layouts.admin')
@section('title', 'Dashboard')
@section('actions')<a href="{{ route('admin.posts.create') }}" class="btn-primary">+ New post</a>@endsection
@section('content')
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['Published', $stats['published'], route('admin.posts.index', ['status' => 'published'])],
        ['Drafts', $stats['drafts'], route('admin.posts.index', ['status' => 'draft'])],
        ['Scheduled', $stats['scheduled'], route('admin.posts.index', ['status' => 'scheduled'])],
        ['Views today', number_format($stats['views_today']), null],
        ['Views (7 days)', number_format($stats['views_week']), null],
        ['Pending comments', $stats['pending_comments'], auth()->user()->canManageAllPosts() ? route('admin.comments.index') : null],
        ['Subscribers', $stats['subscribers'], auth()->user()->isAdmin() ? route('admin.subscribers.index') : null],
        ['Unread messages', $stats['unread_messages'], auth()->user()->isAdmin() ? route('admin.messages.index') : null],
    ] as [$label, $value, $link])
        <a href="{{ $link ?: '#' }}" class="card p-5 {{ $link ? 'hover:border-brand-600' : 'pointer-events-none' }}">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">{{ $label }}</p>
            <p class="mt-1 text-3xl font-black">{{ $value }}</p>
        </a>
    @endforeach
</div>

<div class="mt-8 grid gap-8 xl:grid-cols-3">
    <div class="card xl:col-span-2 overflow-x-auto">
        <h2 class="px-5 py-4 font-bold border-b border-ink-100">Recently edited</h2>
        <table class="table-admin">
            <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Updated</th></tr></thead>
            <tbody>
            @forelse($recentPosts as $post)
                <tr>
                    <td><a href="{{ route('admin.posts.edit', $post) }}" class="font-medium hover:text-brand-600">{{ $post->title }}</a><span class="block text-xs text-ink-500">{{ $post->author?->name }}</span></td>
                    <td>{{ $post->category?->name }}</td>
                    <td>@include('admin.posts._status', ['post' => $post])</td>
                    <td class="whitespace-nowrap text-ink-500">{{ $post->updated_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-8 text-center text-ink-500">No posts yet. <a href="{{ route('admin.posts.create') }}" class="text-brand-600 underline">Write your first story</a>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="space-y-8">
        <div class="card">
            <h2 class="px-5 py-4 font-bold border-b border-ink-100">Trending this week</h2>
            <ol class="divide-y divide-ink-100">
                @forelse($trending as $post)<li class="px-5 py-3 text-sm"><a href="{{ $post->url() }}" target="_blank" class="hover:text-brand-600">{{ $post->title }}</a><span class="block text-xs text-ink-500">{{ number_format($post->views) }} total views</span></li>
                @empty<li class="px-5 py-6 text-sm text-ink-500">No traffic data yet.</li>@endforelse
            </ol>
        </div>
        @if($pendingComments->isNotEmpty())
        <div class="card">
            <h2 class="px-5 py-4 font-bold border-b border-ink-100">Awaiting moderation</h2>
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
