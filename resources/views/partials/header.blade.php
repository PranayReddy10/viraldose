@php
    $navCategories = \App\Models\Category::navigation();
    $breaking = setting('show_breaking_bar') ? \Illuminate\Support\Facades\Cache::remember('breaking.posts', 300, fn () => \App\Models\Post::published()->forListing()->where('is_breaking', true)->orderByDesc('published_at')->limit(8)->get()) : collect();
    $logo = media_url(setting('logo'));
@endphp
<header class="border-b border-ink-100 bg-white sticky top-0 z-40 shadow-sm">
    <div class="container-site">
        <div class="flex items-center justify-between gap-4 py-3">
            <button id="menu-toggle" type="button" class="lg:hidden -ml-2 p-2 text-ink-700" aria-label="Open menu" aria-controls="mobile-menu" aria-expanded="false">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <a href="{{ url('/') }}" class="flex items-center gap-2" aria-label="{{ site_name() }} home">
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ site_name() }}" width="180" height="40" class="h-9 w-auto" fetchpriority="high">
                @else
                    <span class="text-2xl font-black tracking-tight"><span class="text-brand-600">Viral</span>Dose</span>
                @endif
            </a>

            <div class="hidden md:block text-xs text-ink-500">{{ now()->timezone(setting('timezone_display', 'Asia/Kolkata'))->format('l, d M Y') }}</div>

            <button id="search-toggle" type="button" class="-mr-2 p-2 text-ink-700 hover:text-brand-600" aria-label="Search" aria-controls="search-bar" aria-expanded="false">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
            </button>
        </div>

        <form id="search-bar" action="{{ route('search') }}" method="get" role="search" class="hidden pb-3">
            <label for="q" class="sr-only">Search</label>
            <div class="flex gap-2">
                <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Search news…" class="input" required minlength="2">
                <button class="btn-primary" type="submit">Search</button>
            </div>
        </form>

        <nav class="hidden lg:block border-t border-ink-100" aria-label="Main navigation">
            <ul class="flex items-center -mx-3">
                <li><a href="{{ url('/') }}" class="nav-link {{ request()->is('/') ? 'text-brand-600' : '' }}">Home</a></li>
                @foreach($navCategories as $category)
                    <li class="relative group">
                        <a href="{{ $category->url() }}" class="nav-link {{ request()->is('category/'.$category->slug) ? 'text-brand-600' : '' }}">{{ $category->name }}</a>
                        @if($category->children->isNotEmpty())
                            <ul class="invisible absolute left-0 top-full z-50 min-w-48 rounded-b-md border border-ink-100 bg-white py-1 opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                                @foreach($category->children as $child)
                                    <li><a href="{{ $child->url() }}" class="block px-4 py-2 text-sm text-ink-700 hover:bg-ink-100 hover:text-brand-600">{{ $child->name }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>

    {{-- Mobile menu --}}
    <nav id="mobile-menu" class="hidden lg:hidden border-t border-ink-100 bg-white" aria-label="Mobile navigation">
        <ul class="container-site py-2 divide-y divide-ink-100">
            <li><a href="{{ url('/') }}" class="block py-3 font-semibold">Home</a></li>
            @foreach($navCategories as $category)
                <li>
                    <a href="{{ $category->url() }}" class="block py-3 font-semibold">{{ $category->name }}</a>
                    @if($category->children->isNotEmpty())
                        <ul class="pb-2 pl-4 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-700">
                            @foreach($category->children as $child)<li><a href="{{ $child->url() }}" class="hover:text-brand-600">{{ $child->name }}</a></li>@endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
            <li><a href="{{ route('contact') }}" class="block py-3 font-semibold">Contact</a></li>
        </ul>
    </nav>

    {{-- Mobile horizontal category strip --}}
    <div class="lg:hidden border-t border-ink-100 overflow-x-auto no-scrollbar">
        <ul class="container-site flex gap-1 py-1">
            @foreach($navCategories as $category)
                <li><a href="{{ $category->url() }}" class="block whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold uppercase {{ request()->is('category/'.$category->slug) ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-700' }}">{{ $category->name }}</a></li>
            @endforeach
        </ul>
    </div>
</header>

@if($breaking->isNotEmpty())
    <div class="bg-ink-900 text-white">
        <div class="container-site flex items-center gap-3 overflow-hidden py-2 text-sm">
            <span class="shrink-0 rounded bg-brand-600 px-2 py-0.5 text-xs font-bold uppercase">Breaking</span>
            <div class="relative flex-1 overflow-hidden">
                <div class="ticker flex w-max gap-10 whitespace-nowrap">
                    @foreach($breaking->concat($breaking) as $item)
                        <a href="{{ $item->url() }}" class="hover:text-brand-500">{{ $item->title }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif

@include('partials.ad', ['slot' => 'header'])
