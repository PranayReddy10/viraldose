@extends('layouts.app')
@section('content')
<x-archive :posts="$posts" :title="$q !== '' ? 'Results for “'.$q.'”' : 'Search'">
    <x-slot:before>
        <form action="{{ route('search') }}" method="get" role="search" class="mb-8 flex gap-2">
            <label for="search-q" class="sr-only">Search</label>
            <input id="search-q" type="search" name="q" value="{{ $q }}" placeholder="Search news…" class="input" required minlength="2">
            <button class="btn-primary" type="submit">Search</button>
        </form>
        @if($posts)<p class="mb-6 text-sm text-ink-500">{{ $posts->total() }} {{ \Illuminate\Support\Str::plural('result', $posts->total()) }}</p>@endif
    </x-slot:before>
</x-archive>
@endsection
