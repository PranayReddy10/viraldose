@props(['post', 'variant' => 'grid', 'eager' => false, 'heading' => 'h3'])
@php $cat = $post->category; @endphp

@if($variant === 'hero')
    <article class="group relative overflow-hidden rounded-lg bg-ink-900 text-white aspect-[4/3] sm:aspect-[16/9]">
        <x-post-image :post="$post" size="large" sizes="(min-width: 1024px) 66vw, 100vw" :eager="$eager" class="opacity-90 transition group-hover:scale-105 duration-500" />
        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>
        <div class="absolute inset-x-0 bottom-0 p-4 sm:p-6">
            @if($cat)<a href="{{ $cat->url() }}" class="cat-badge" style="background: {{ $cat->color }}">{{ $cat->name }}</a>@endif
            <{{ $heading }} class="mt-2 text-xl font-extrabold leading-tight sm:text-3xl"><a href="{{ $post->url() }}" class="hover:underline">{{ $post->title }}</a></{{ $heading }}>
            <p class="mt-2 hidden text-sm text-gray-200 sm:block line-clamp-2">{{ $post->excerpt }}</p>
            <p class="mt-2 text-xs text-gray-300"><time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->diffForHumans() }}</time> · {{ $post->reading_time }} min read</p>
        </div>
    </article>

@elseif($variant === 'overlay')
    <article class="group relative overflow-hidden rounded-lg bg-ink-900 text-white aspect-[4/3]">
        <x-post-image :post="$post" size="medium" sizes="(min-width: 1024px) 33vw, 50vw" :eager="$eager" class="opacity-90 transition group-hover:scale-105 duration-500" />
        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-transparent"></div>
        <div class="absolute inset-x-0 bottom-0 p-3 sm:p-4">
            @if($cat)<a href="{{ $cat->url() }}" class="cat-badge" style="background: {{ $cat->color }}">{{ $cat->name }}</a>@endif
            <{{ $heading }} class="mt-1 text-sm font-bold leading-snug sm:text-base line-clamp-3"><a href="{{ $post->url() }}" class="hover:underline">{{ $post->title }}</a></{{ $heading }}>
        </div>
    </article>

@elseif($variant === 'list')
    <article class="flex gap-4">
        <a href="{{ $post->url() }}" class="block w-28 shrink-0 overflow-hidden rounded-md aspect-[4/3] sm:w-40" aria-hidden="true" tabindex="-1">
            <x-post-image :post="$post" size="small" sizes="160px" />
        </a>
        <div class="min-w-0 flex-1">
            @if($cat)<a href="{{ $cat->url() }}" class="text-[11px] font-bold uppercase tracking-wider" style="color: {{ $cat->color }}">{{ $cat->name }}</a>@endif
            <{{ $heading }} class="mt-0.5 font-bold leading-snug sm:text-lg line-clamp-2"><a href="{{ $post->url() }}" class="hover:text-brand-600">{{ $post->title }}</a></{{ $heading }}>
            <p class="mt-1 hidden text-sm text-ink-500 sm:block line-clamp-2">{{ $post->excerpt }}</p>
            <p class="mt-1 text-xs text-ink-500"><time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('M d, Y') }}</time></p>
        </div>
    </article>

@elseif($variant === 'compact')
    <article class="flex gap-3">
        <a href="{{ $post->url() }}" class="block w-20 shrink-0 overflow-hidden rounded aspect-square" aria-hidden="true" tabindex="-1">
            <x-post-image :post="$post" size="small" sizes="80px" />
        </a>
        <div class="min-w-0">
            <{{ $heading }} class="text-sm font-semibold leading-snug line-clamp-3"><a href="{{ $post->url() }}" class="hover:text-brand-600">{{ $post->title }}</a></{{ $heading }}>
            <p class="mt-1 text-xs text-ink-500"><time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->diffForHumans() }}</time></p>
        </div>
    </article>

@else
    <article class="group">
        <a href="{{ $post->url() }}" class="block overflow-hidden rounded-lg aspect-video" aria-hidden="true" tabindex="-1">
            <x-post-image :post="$post" size="medium" :eager="$eager" class="transition duration-500 group-hover:scale-105" />
        </a>
        <div class="mt-3">
            @if($cat)<a href="{{ $cat->url() }}" class="text-[11px] font-bold uppercase tracking-wider" style="color: {{ $cat->color }}">{{ $cat->name }}</a>@endif
            <{{ $heading }} class="mt-1 text-lg font-bold leading-snug line-clamp-2"><a href="{{ $post->url() }}" class="hover:text-brand-600">{{ $post->title }}</a></{{ $heading }}>
            <p class="mt-1 text-sm text-ink-500 line-clamp-2">{{ $post->excerpt }}</p>
            <p class="mt-2 text-xs text-ink-500">
                @if($post->author)<a href="{{ $post->author->url() }}" class="font-medium text-ink-700 hover:text-brand-600">{{ $post->author->name }}</a> · @endif
                <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('M d, Y') }}</time>
            </p>
        </div>
    </article>
@endif
