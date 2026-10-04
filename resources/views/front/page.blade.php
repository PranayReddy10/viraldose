@extends('layouts.app')
@section('content')
<div class="container-site py-6">
    <x-breadcrumbs />
    <div class="grid gap-10 lg:grid-cols-3">
        <article class="lg:col-span-2">
            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">{{ $page->title }}</h1>
            <p class="mt-1 text-xs text-ink-500">Last updated <time datetime="{{ $page->updated_at->toIso8601String() }}">{{ $page->updated_at->format('M d, Y') }}</time></p>
            <div class="article-body mt-6">{!! $page->content !!}</div>
        </article>
        @include('partials.sidebar')
    </div>
</div>
@endsection
