@extends('layouts.admin')
@section('title', 'Reels')
@section('actions')<a href="{{ route('admin.reels.create') }}" class="btn-primary !px-3 !py-1.5 text-xs"><x-admin.icon name="plus" class="h-4 w-4" /> Add Reel</a> <a href="{{ route('reels.index') }}" target="_blank" class="btn-outline !px-3 !py-1.5 text-xs">Open /reels</a>@endsection
@section('content')
<p class="mb-4 text-sm text-ink-700">Reels appear in the vertical, swipe-to-scroll feed at <code>/reels</code> and as a strip on the home page. Sources: uploaded MP4, direct video URL, YouTube Shorts, or an Instagram reel.</p>
<form method="get" class="mb-4 flex gap-2"><input type="search" name="q" value="{{ request('q') }}" placeholder="Search reels…" class="input !w-auto min-w-60"><button class="btn-secondary">Search</button></form>
@if($reels->isEmpty())
    <div class="card p-10 text-center text-ink-500">No reels yet. <a href="{{ route('admin.reels.create') }}" class="text-brand-600 underline">Add the first reel</a>.</div>
@else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
        @foreach($reels as $reel)
            <div class="card overflow-hidden">
                <a href="{{ route('admin.reels.edit', $reel) }}" class="relative block aspect-[9/16] bg-ink-900">
                    @if($reel->thumbnailUrl())<img src="{{ $reel->thumbnailUrl() }}" alt="" class="h-full w-full object-cover">@else<div class="flex h-full items-center justify-center p-4 text-center text-sm font-bold text-white">{{ $reel->title }}</div>@endif
                    <span class="absolute left-2 top-2 rounded bg-black/70 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">{{ $reel->source_type }}</span>
                    @unless($reel->is_active)<span class="absolute right-2 top-2 rounded bg-yellow-500 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">Hidden</span>@endunless
                    <span class="absolute bottom-2 right-2 rounded bg-black/70 px-1.5 py-0.5 text-[10px] text-white">{{ number_format($reel->views) }} views</span>
                </a>
                <div class="p-3">
                    <p class="line-clamp-2 text-sm font-semibold">{{ $reel->title }}</p>
                    <p class="mt-1 text-xs text-ink-500">{{ $reel->category?->name ?? '—' }} · {{ $reel->published_at?->diffForHumans() }}</p>
                    <div class="mt-2 flex gap-3 text-xs font-semibold">
                        <a href="{{ route('admin.reels.edit', $reel) }}" class="text-brand-600">Edit</a>
                        <a href="{{ $reel->url() }}" target="_blank" class="text-ink-500">View</a>
                        <x-admin.delete-button :action="route('admin.reels.destroy', $reel)" class="ml-auto" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="mt-4">{{ $reels->links() }}</div>
@endif
@endsection
