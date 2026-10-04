@php
    $sidebar = \Illuminate\Support\Facades\Cache::remember('sidebar.data', 600, fn () => [
        'trending' => \App\Models\Post::trending(7, 6),
        'popular_tags' => \App\Models\Tag::withCount(['posts' => fn ($q) => $q->published()])->orderByDesc('posts_count')->limit(20)->get()->where('posts_count', '>', 0),
    ]);
@endphp
<aside class="space-y-8" aria-label="Sidebar">
    @include('partials.ad', ['slot' => 'sidebar_top'])

    @if($sidebar['trending']->isNotEmpty())
        <section>
            <h2 class="section-title">Trending</h2>
            <ol class="space-y-4">
                @foreach($sidebar['trending'] as $i => $item)
                    <li class="flex gap-3">
                        <span class="text-3xl font-black leading-none text-ink-300">{{ $i + 1 }}</span>
                        <div>
                            <a href="{{ $item->category->url() }}" class="text-[11px] font-bold uppercase" style="color: {{ $item->category->color }}">{{ $item->category->name }}</a>
                            <h3 class="font-semibold leading-snug line-clamp-2"><a href="{{ $item->url() }}" class="hover:text-brand-600">{{ $item->title }}</a></h3>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="card p-5 bg-ink-100">
        <h2 class="text-lg font-extrabold">Newsletter</h2>
        <p class="mt-1 text-sm text-ink-700">Daily top stories, no spam.</p>
        @if(session('newsletter_status'))
            <p class="mt-3 text-sm text-green-700">{{ session('newsletter_status') }}</p>
        @else
            <form action="{{ route('newsletter.subscribe') }}" method="post" class="mt-3 space-y-2">
                @csrf
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                <label for="sidebar-email" class="sr-only">Email</label>
                <input id="sidebar-email" type="email" name="email" required placeholder="you@example.com" class="input">
                <button class="btn-primary w-full" type="submit">Subscribe</button>
            </form>
        @endif
    </section>

    @if($sidebar['popular_tags']->isNotEmpty())
        <section>
            <h2 class="section-title">Topics</h2>
            <ul class="flex flex-wrap gap-2">
                @foreach($sidebar['popular_tags'] as $tag)
                    <li><a href="{{ $tag->url() }}" class="inline-block rounded bg-ink-100 px-2.5 py-1 text-xs font-semibold text-ink-700 hover:bg-brand-600 hover:text-white">#{{ $tag->name }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @include('partials.ad', ['slot' => 'sidebar_bottom'])
</aside>
