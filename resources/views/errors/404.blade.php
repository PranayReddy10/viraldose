@extends('layouts.app')
@php app(\App\Services\Seo::class)->title('Page not found')->noindex(false); @endphp
@section('content')
<div class="container-site py-16 text-center">
    <p class="text-7xl font-black text-brand-600">404</p>
    <h1 class="mt-4 text-2xl font-bold">We couldn't find that page</h1>
    <p class="mt-2 text-ink-500">The link may be broken or the story may have moved. Try a search instead.</p>
    <form action="{{ route('search') }}" method="get" class="mx-auto mt-6 flex max-w-md gap-2">
        <input type="search" name="q" placeholder="Search news…" class="input" required minlength="2" aria-label="Search">
        <button class="btn-primary" type="submit">Search</button>
    </form>
    <a href="{{ url('/') }}" class="mt-6 inline-block text-brand-600 font-semibold hover:underline">← Back to home</a>
    @php $latest = \App\Models\Post::published()->forListing()->orderByDesc('published_at')->limit(6)->get(); @endphp
    @if($latest->isNotEmpty())
        <section class="mt-14 text-left">
            <h2 class="section-title">Latest stories</h2>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($latest as $post)<x-post-card :post="$post" heading="h3" />@endforeach
            </div>
        </section>
    @endif
</div>
@endsection
