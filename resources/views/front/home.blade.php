@extends('layouts.app')

@section('content')
<div class="container-site py-6">
    <h1 class="sr-only">{{ site_name() }} - {{ setting('site_tagline') }}</h1>

    {{-- Hero --}}
    @if($slider->isNotEmpty())
        <section aria-label="Top stories" class="grid gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <x-post-card :post="$slider->first()" variant="hero" heading="h2" :eager="true" />
            </div>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-1">
                @foreach($slider->slice(1, 2) as $post)
                    <x-post-card :post="$post" variant="overlay" heading="h2" :eager="true" />
                @endforeach
            </div>
            @if($slider->count() > 3)
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:col-span-3">
                    @foreach($slider->slice(3) as $post)
                        <x-post-card :post="$post" variant="overlay" heading="h2" />
                    @endforeach
                    @foreach($featured->take(4 - max(0, $slider->count() - 3)) as $post)
                        <x-post-card :post="$post" variant="overlay" heading="h2" />
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <div class="mt-10 grid gap-10 lg:grid-cols-3">
        <div class="space-y-12 lg:col-span-2">
            @if($featured->isNotEmpty())
                <section>
                    <h2 class="section-title">Featured</h2>
                    <div class="grid gap-6 sm:grid-cols-2">
                        @foreach($featured as $post)
                            <x-post-card :post="$post" heading="h3" />
                        @endforeach
                    </div>
                </section>
            @endif

            <section>
                <h2 class="section-title">Latest News</h2>
                <div class="space-y-6">
                    @forelse($latest as $post)
                        <x-post-card :post="$post" variant="list" heading="h3" />
                    @empty
                        <p class="text-ink-500">No articles published yet. <a href="{{ route('admin.login') }}" class="text-brand-600 underline">Log in</a> to add your first story.</p>
                    @endforelse
                </div>
            </section>

            @include('partials.ad', ['slot' => 'home_middle'])

            @foreach($sections as $i => $section)
                <section>
                    <h2 class="section-title"><a href="{{ $section['category']->url() }}" class="hover:text-brand-600" style="color: {{ $section['category']->color }}">{{ $section['category']->name }}</a></h2>
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div class="sm:col-span-2 lg:col-span-1">
                            <x-post-card :post="$section['posts']->first()" heading="h3" />
                        </div>
                        <div class="space-y-4">
                            @foreach($section['posts']->slice(1) as $post)
                                <x-post-card :post="$post" variant="compact" heading="h3" />
                            @endforeach
                        </div>
                    </div>
                    <a href="{{ $section['category']->url() }}" class="mt-4 inline-block text-sm font-semibold text-brand-600 hover:underline">More in {{ $section['category']->name }} →</a>
                </section>
            @endforeach

            @if($recommended->isNotEmpty())
                <section>
                    <h2 class="section-title">Recommended</h2>
                    <div class="grid gap-6 grid-cols-2 lg:grid-cols-3">
                        @foreach($recommended as $post)
                            <x-post-card :post="$post" variant="overlay" heading="h3" />
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        @include('partials.sidebar')
    </div>
</div>
@endsection
