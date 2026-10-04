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

    @include('partials.ad', ['slot' => 'home_after_hero'])

    @if($reels->isNotEmpty())
        <section class="mt-8" aria-label="Reels">
            <h2 class="section-title"><a href="{{ route('reels.index') }}" class="flex items-center gap-2 hover:text-brand-600"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4zM4 9h16M9 4v16M15 4v16"/></svg>Reels</a></h2>
            <div class="-mx-4 flex gap-3 overflow-x-auto px-4 pb-2 no-scrollbar sm:mx-0 sm:px-0">
                @foreach($reels as $reel)
                    <a href="{{ $reel->url() }}" class="group relative w-32 shrink-0 overflow-hidden rounded-lg bg-ink-900 aspect-[9/16] sm:w-40">
                        @if($reel->thumbnailUrl())<img src="{{ $reel->thumbnailUrl() }}" alt="{{ $reel->title }}" width="400" height="711" loading="lazy" decoding="async" class="h-full w-full object-cover opacity-90 transition group-hover:scale-105">@endif
                        <span class="absolute inset-0 bg-gradient-to-t from-black/90 via-transparent to-transparent"></span>
                        <span class="absolute left-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-white/20 text-white"><svg class="ml-0.5 h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
                        <span class="absolute inset-x-0 bottom-0 p-2 text-xs font-bold leading-snug text-white line-clamp-3">{{ $reel->title }}</span>
                    </a>
                @endforeach
                <a href="{{ route('reels.index') }}" class="flex w-32 shrink-0 items-center justify-center rounded-lg border-2 border-dashed border-ink-300 text-center text-sm font-semibold text-ink-700 aspect-[9/16] hover:border-brand-600 hover:text-brand-600 sm:w-40">See all<br>reels →</a>
            </div>
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
                        @if($loop->iteration % 5 === 0 && ! $loop->last)@include('partials.ad', ['slot' => 'home_in_latest'])@endif
                    @empty
                        <p class="text-ink-500">No articles published yet. <a href="{{ route('admin.login') }}" class="text-brand-600 underline">Log in</a> to add your first story.</p>
                    @endforelse
                </div>
            </section>

            @foreach($sections as $i => $section)
                @if($i > 0 && $i % 2 === 0)@include('partials.ad', ['slot' => 'home_middle'])@endif
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
