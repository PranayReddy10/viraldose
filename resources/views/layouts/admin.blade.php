@php $user = auth()->user(); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ site_name() }} Admin</title>
    <link rel="icon" href="{{ media_url(setting('favicon')) ?: asset('favicon.svg') }}">
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @stack('head')
</head>
<body class="bg-[#f4f5f7] text-ink-900" data-site-name="{{ site_name() }}" data-site-url="{{ rtrim(config('app.url'), '/') }}">
<div class="min-h-screen lg:flex">
    {{-- Sidebar --}}
    <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-[#222d32] text-gray-300 transition-transform lg:static lg:translate-x-0 lg:shrink-0">
        <div class="flex h-14 items-center gap-2 bg-[#1a2226] px-5 text-white">
            <span class="text-lg font-bold tracking-tight">{{ site_name() }}</span>
            <span class="text-xs text-gray-400">· Admin</span>
        </div>
        <div class="flex items-center gap-3 border-b border-white/5 px-5 py-4">
            <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-sm font-bold text-white">
                @if($user->avatarUrl())<img src="{{ $user->avatarUrl() }}" alt="" class="h-full w-full object-cover">@else{{ strtoupper(mb_substr($user->name, 0, 1)) }}@endif
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
                <p class="flex items-center gap-1 text-xs text-gray-400"><span class="inline-block h-2 w-2 rounded-full bg-green-500"></span> online · {{ ucfirst($user->role) }}</p>
            </div>
        </div>
        @php
            $nav = [
                ['MAIN NAVIGATION', null, null, null],
                ['Dashboard', 'admin.dashboard', 'admin', 'home', null],
                ['Add Post', 'admin.posts.create', 'admin/posts/create', 'plus', null],
                ['Posts', 'admin.posts.index', 'admin/posts', 'posts', null],
                ['Categories', 'admin.categories.index', 'admin/categories*', 'folder', ['admin', 'editor']],
                ['Tags', 'admin.tags.index', 'admin/tags*', 'tag', ['admin', 'editor']],
                ['Pages', 'admin.pages.index', 'admin/pages*', 'page', ['admin', 'editor']],
                ['Comments', 'admin.comments.index', 'admin/comments*', 'comments', ['admin', 'editor']],
                ['Reels', 'admin.reels.index', 'admin/reels*', 'reels', null],
                ['RSS Feeds', 'admin.feeds.index', 'admin/feeds*', 'rss', ['admin', 'editor']],
                ['SEO & GROWTH', null, null, null],
                ['Google Search & Analytics', 'admin.google.index', 'admin/google*', 'google', ['admin', 'editor']],
                ['Instagram', 'admin.settings.edit', 'admin/settings?tab=instagram', 'instagram', ['admin'], ['tab' => 'instagram']],
                ['Ad Spaces', 'admin.ads.index', 'admin/ads*', 'ads', ['admin']],
                ['Redirects', 'admin.redirects.index', 'admin/redirects*', 'redirect', ['admin']],
                ['Newsletter', 'admin.subscribers.index', 'admin/subscribers*', 'mail', ['admin']],
                ['Contact Messages', 'admin.messages.index', 'admin/messages*', 'inbox', ['admin']],
                ['SYSTEM', null, null, null],
                ['Users', 'admin.users.index', 'admin/users*', 'users', ['admin']],
                ['Storage', 'admin.settings.edit', 'admin/settings?tab=storage', 'cloud', ['admin'], ['tab' => 'storage']],
                ['Settings', 'admin.settings.edit', 'admin/settings', 'settings', ['admin']],
            ];
        @endphp
        <nav class="flex-1 overflow-y-auto py-2">
            @foreach($nav as $item)
                @if($item[1] === null)
                    <p class="px-5 pb-1 pt-4 text-[10px] font-bold uppercase tracking-widest text-gray-500">{{ $item[0] }}</p>
                    @continue
                @endif
                @php [$label, $route, $pattern, $icon, $roles] = $item; $params = $item[5] ?? []; @endphp
                @if($roles === null || $user->hasRole(...$roles))
                    @php $active = $params ? request()->fullUrlIs(route($route, $params)) : (request()->is($pattern) && ! ($pattern === 'admin/posts' && request()->is('admin/posts/create')) && ! ($pattern === 'admin/settings' && in_array(request()->query('tab'), ['storage', 'instagram'], true))); @endphp
                    <a href="{{ route($route, $params) }}" class="flex items-center gap-3 border-l-4 px-4 py-2.5 text-sm {{ $active ? 'border-brand-600 bg-[#1e282c] text-white' : 'border-transparent hover:bg-[#1e282c] hover:text-white' }}">
                        <x-admin.icon :name="$icon" class="h-4.5 w-4.5 shrink-0" />{{ $label }}
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="border-t border-white/5 p-4 text-xs text-gray-400">
            <a href="{{ route('admin.profile.edit') }}" class="hover:text-white">My profile</a> ·
            <form method="post" action="{{ route('admin.logout') }}" class="inline">@csrf<button class="hover:text-white">Sign out</button></form>
            <p class="mt-1">ViralDose v2.0 · Laravel {{ app()->version() }}</p>
        </div>
    </aside>

    <div class="flex-1 min-w-0">
        {{-- Top bar --}}
        <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-ink-300/50 bg-white px-4 lg:px-6">
            <div class="flex items-center gap-3">
                <button id="admin-sidebar-toggle" class="-ml-2 rounded p-2 hover:bg-ink-100 lg:hidden" aria-label="Toggle menu"><x-admin.icon name="menu" class="h-6 w-6" /></button>
                <h1 class="text-base font-bold lg:text-lg">@yield('title', 'Dashboard')</h1>
            </div>
            <div class="flex items-center gap-2">
                @yield('actions')
                <a href="{{ url('/') }}" target="_blank" class="btn bg-green-600 text-white hover:bg-green-700 !px-3 !py-1.5 text-xs"><x-admin.icon name="eye" class="h-4 w-4" /> View Site</a>
                <a href="{{ route('admin.profile.edit') }}" class="hidden h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-ink-900 text-xs font-bold text-white sm:flex" title="My profile">
                    @if($user->avatarUrl())<img src="{{ $user->avatarUrl() }}" alt="" class="h-full w-full object-cover">@else{{ strtoupper(mb_substr($user->name, 0, 1)) }}@endif
                </a>
            </div>
        </header>
        <main class="p-4 lg:p-6">
            @if(session('status'))<div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>@endif
            @if(session('warning'))<div class="mb-4 rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">{{ session('warning') }}</div>@endif
            @if($errors->any())
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
