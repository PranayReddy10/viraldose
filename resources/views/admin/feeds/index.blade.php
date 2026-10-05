@extends('layouts.admin')
@section('title', 'RSS Feeds')
@section('actions')<a href="{{ route('admin.feeds.create') }}" class="btn-primary !px-3 !py-1.5 text-xs"><x-admin.icon name="plus" class="h-4 w-4" /> Add Feed</a>@endsection
@section('content')
<p class="mb-4 text-sm text-ink-700">Feeds are fetched automatically every hour (<code>feeds:import</code> in the scheduler). Imported stories arrive as drafts unless auto-publish is on. With <strong>full article</strong> on, the source page is downloaded and the whole story (text and images) is imported instead of the feed teaser. Rewrite imported stories before publishing — duplicate content from other sites is exactly what Google refuses to index.</p>
<div class="card overflow-x-auto">
    <table class="table-admin">
        <thead><tr><th>Feed</th><th>Category</th><th>Author</th><th>Mode</th><th>Imported</th><th>Last fetch</th><th></th></tr></thead>
        <tbody>
        @forelse($feeds as $feed)
            <tr>
                <td><span class="font-medium">{{ $feed->name }}</span><a href="{{ $feed->url }}" target="_blank" class="block max-w-xs truncate text-xs text-ink-500 hover:text-brand-600">{{ $feed->url }}</a>@if($feed->last_error)<span class="block text-xs text-red-600">{{ \Illuminate\Support\Str::limit($feed->last_error, 100) }}</span>@endif</td>
                <td>{{ $feed->category?->name }}</td>
                <td>{{ $feed->user?->name }}</td>
                <td>{!! $feed->is_active ? ($feed->auto_publish ? '<span class="badge-green">auto-publish</span>' : '<span class="badge-yellow">drafts</span>') : '<span class="badge-gray">paused</span>' !!}@if($feed->fetch_full_content)<span class="block text-xs text-ink-500">full article</span>@endif</td>
                <td>{{ $feed->posts_count }} posts</td>
                <td class="text-ink-500">{{ $feed->last_fetched_at?->diffForHumans() ?? 'never' }}</td>
                <td class="whitespace-nowrap text-right">
                    <form method="post" action="{{ route('admin.feeds.fetch', $feed) }}" class="inline">@csrf<button class="text-xs font-semibold text-green-700 hover:underline">Fetch now</button></form>
                    <form method="post" action="{{ route('admin.feeds.refill', $feed) }}" class="inline">@csrf<button class="ml-2 text-xs font-semibold text-brand-600 hover:underline" title="Download the full article for imported posts that only hold the feed teaser">Fill content</button></form>
                    <a href="{{ route('admin.feeds.edit', $feed) }}" class="ml-2 text-xs font-semibold text-brand-600 hover:underline">Edit</a>
                    <x-admin.delete-button :action="route('admin.feeds.destroy', $feed)" class="ml-2" confirm="Delete this feed? Imported posts are kept." />
                </td>
            </tr>
        @empty<tr><td colspan="7" class="py-8 text-center text-ink-500">No feeds yet.</td></tr>@endforelse
        </tbody>
    </table>
</div>
@endsection
