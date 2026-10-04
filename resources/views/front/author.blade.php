@extends('layouts.app')
@section('content')
<x-archive :posts="$posts" :title="$author->name">
    <x-slot:heading>
        <div class="flex items-center gap-4">
            @if($author->avatarUrl())<img src="{{ $author->avatarUrl() }}" alt="{{ $author->name }}" width="80" height="80" class="h-20 w-20 rounded-full object-cover">@endif
            <div>
                <h1 class="text-3xl font-black tracking-tight">{{ $author->name }}</h1>
                @if($author->bio)<p class="mt-1 max-w-2xl text-ink-700">{{ $author->bio }}</p>@endif
                <p class="mt-1 text-sm text-ink-500">{{ $posts->total() }} {{ \Illuminate\Support\Str::plural('article', $posts->total()) }}
                    @if($author->website) · <a href="{{ $author->website }}" rel="noopener nofollow" class="underline">Website</a>@endif
                    @if($author->twitter) · <a href="{{ str_starts_with($author->twitter, 'http') ? $author->twitter : 'https://x.com/'.ltrim($author->twitter, '@') }}" rel="noopener nofollow" class="underline">X</a>@endif
                </p>
            </div>
        </div>
    </x-slot:heading>
</x-archive>
@endsection
