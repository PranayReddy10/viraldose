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
<body class="bg-ink-100 text-ink-900" data-site-name="{{ site_name() }}" data-site-url="{{ rtrim(config('app.url'), '/') }}">
<div class="min-h-screen lg:flex">
    <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full bg-gray-900 text-white transition-transform lg:static lg:translate-x-0 lg:shrink-0">
        <div class="flex h-16 items-center justify-between px-5 border-b border-white/10">
            <a href="{{ route('admin.dashboard') }}" class="text-xl font-black"><span class="text-brand-500">Viral</span>Dose</a>
            <a href="{{ url('/') }}" target="_blank" class="text-xs text-gray-400 hover:text-white">View site ↗</a>
        </div>
        @php
            $nav = [
                ['admin.dashboard', 'Dashboard', 'admin', null],
                ['admin.posts.index', 'Posts', 'admin/posts*', null],
                ['admin.categories.index', 'Categories', 'admin/categories*', ['admin', 'editor']],
                ['admin.tags.index', 'Tags', 'admin/tags*', ['admin', 'editor']],
                ['admin.pages.index', 'Pages', 'admin/pages*', ['admin', 'editor']],
                ['admin.comments.index', 'Comments', 'admin/comments*', ['admin', 'editor']],
                ['admin.ads.index', 'Ads', 'admin/ads*', ['admin']],
                ['admin.redirects.index', 'Redirects', 'admin/redirects*', ['admin']],
                ['admin.users.index', 'Users', 'admin/users*', ['admin']],
                ['admin.subscribers.index', 'Subscribers', 'admin/subscribers*', ['admin']],
                ['admin.messages.index', 'Messages', 'admin/messages*', ['admin']],
                ['admin.settings.edit', 'Settings', 'admin/settings*', ['admin']],
            ];
        @endphp
        <nav class="space-y-1 p-3">
            @foreach($nav as [$route, $label, $pattern, $roles])
                @if($roles === null || $user->hasRole(...$roles))
                    <a href="{{ route($route) }}" class="admin-nav-link {{ request()->is($pattern) ? 'active' : '' }}">{{ $label }}</a>
                @endif
            @endforeach
        </nav>
        <div class="absolute bottom-0 w-full border-t border-white/10 p-4 text-sm">
            <a href="{{ route('admin.profile.edit') }}" class="block font-semibold hover:text-brand-500">{{ $user->name }}</a>
            <p class="text-xs text-gray-400 capitalize">{{ $user->role }}</p>
            <form method="post" action="{{ route('admin.logout') }}" class="mt-2">@csrf<button class="text-xs text-gray-400 hover:text-white">Sign out</button></form>
        </div>
    </aside>

    <div class="flex-1 min-w-0">
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-ink-300/50 bg-white px-4 lg:px-8">
            <div class="flex items-center gap-3">
                <button id="admin-sidebar-toggle" class="lg:hidden p-2 -ml-2" aria-label="Toggle menu"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                <h1 class="text-lg font-bold">@yield('title', 'Dashboard')</h1>
            </div>
            <div>@yield('actions')</div>
        </header>
        <main class="p-4 lg:p-8">
            @if(session('status'))<div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>@endif
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
