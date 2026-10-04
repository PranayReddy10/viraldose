@extends('layouts.admin')
@section('title', 'Google Search & Analytics')
@section('actions')
    <form method="post" action="{{ route('admin.google.refresh') }}">@csrf<button class="btn-outline !px-3 !py-1.5 text-xs"><x-admin.icon name="refresh" class="h-4 w-4" /> Refresh data</button></form>
@endsection
@section('content')
@php $tabs = ['overview' => 'Search performance', 'analytics' => 'Analytics (GA4)', 'indexing' => 'Indexing', 'sitemaps' => 'Sitemaps']; @endphp

@if(! $configured)
    <div class="card mx-auto max-w-2xl p-8 text-center">
        <x-admin.icon name="google" class="mx-auto h-12 w-12 text-ink-500" />
        <h2 class="mt-3 text-xl font-bold">Connect Google</h2>
        <p class="mt-2 text-sm text-ink-700">Upload a service-account key once to get Search Console clicks &amp; impressions, live index status for every article, automatic submission of new posts to the Indexing API, sitemap status and GA4 traffic — all inside this dashboard.</p>
        @if(auth()->user()->isAdmin())<a href="{{ route('admin.settings.edit', ['tab' => 'google']) }}" class="btn-primary mt-5">Go to Settings → Google &amp; Indexing</a>@endif
        <p class="mt-6 text-xs text-ink-500">IndexNow (Bing / Yandex) works without Google: key file <a href="{{ url('/'.$indexNowKey.'.txt') }}" target="_blank" class="underline">/{{ $indexNowKey }}.txt</a></p>
    </div>
@else
    <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        <span class="rounded bg-green-50 px-3 py-1 text-green-800">Connected: <strong>{{ $clientEmail }}</strong></span>
        <span class="rounded bg-ink-100 px-3 py-1">Property: <strong>{{ $siteUrl }}</strong></span>
        <form method="post" action="{{ route('admin.google.test') }}">@csrf<button class="text-brand-600 underline">Test connection</button></form>
        <div class="ml-auto flex gap-1">
            @foreach([7, 28, 90] as $d)<a href="{{ route('admin.google.index', ['tab' => $tab, 'days' => $d]) }}" class="rounded px-2 py-1 text-xs font-semibold {{ $days === $d ? 'bg-ink-900 text-white' : 'bg-white border border-ink-300' }}">{{ $d }}d</a>@endforeach
        </div>
    </div>
    <div class="mb-6 flex flex-wrap gap-1 border-b border-ink-300/60">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.google.index', ['tab' => $key, 'days' => $days]) }}" class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold {{ $tab === $key ? 'border-brand-600 text-brand-600' : 'border-transparent text-ink-500 hover:text-ink-900' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if($tab === 'overview')
        @if(isset($apiErrors['search_console']))
            <div class="mb-4 rounded bg-red-50 px-4 py-3 text-sm text-red-700">Search Console: {{ $apiErrors['search_console'] }}<br><span class="text-xs">Add <strong>{{ $clientEmail }}</strong> as a user of the property in Search Console → Settings → Users and permissions.</span></div>
        @elseif($sc)
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([['Total clicks', number_format($sc['totals']['clicks'])], ['Total impressions', number_format($sc['totals']['impressions'])], ['Average CTR', $sc['totals']['ctr'].'%'], ['Average position', $sc['totals']['position']]] as [$l, $v])
                    <div class="card p-4"><p class="text-xs font-semibold uppercase text-ink-500">{{ $l }}</p><p class="text-3xl font-black">{{ $v }}</p></div>
                @endforeach
            </div>
            <div class="mt-6 grid gap-6 xl:grid-cols-2">
                <section class="card p-5"><h2 class="mb-2 font-bold">Clicks per day</h2><x-admin.bar-chart :series="array_map(fn ($r) => ['date' => $r['key'], 'value' => $r['clicks']], $sc['daily'])" label="clicks" color="#2563eb" /></section>
                <section class="card p-5"><h2 class="mb-2 font-bold">Impressions per day</h2><x-admin.bar-chart :series="array_map(fn ($r) => ['date' => $r['key'], 'value' => $r['impressions']], $sc['daily'])" label="impressions" color="#7c3aed" /></section>
            </div>
            <div class="mt-6 grid gap-6 xl:grid-cols-2">
                <section class="card overflow-x-auto"><h2 class="border-b border-ink-100 px-5 py-3 font-bold">Top queries</h2>
                    <table class="table-admin"><thead><tr><th>Query</th><th class="text-right">Clicks</th><th class="text-right">Impr.</th><th class="text-right">CTR</th><th class="text-right">Pos.</th></tr></thead><tbody>
                    @forelse($sc['queries'] as $row)<tr><td>{{ $row['key'] }}</td><td class="text-right tabular-nums">{{ $row['clicks'] }}</td><td class="text-right tabular-nums">{{ number_format($row['impressions']) }}</td><td class="text-right tabular-nums">{{ $row['ctr'] }}%</td><td class="text-right tabular-nums">{{ $row['position'] }}</td></tr>
                    @empty<tr><td colspan="5" class="py-6 text-center text-ink-500">No data yet for this period.</td></tr>@endforelse
                    </tbody></table></section>
                <section class="card overflow-x-auto"><h2 class="border-b border-ink-100 px-5 py-3 font-bold">Top pages</h2>
                    <table class="table-admin"><thead><tr><th>Page</th><th class="text-right">Clicks</th><th class="text-right">Impr.</th><th class="text-right">CTR</th></tr></thead><tbody>
                    @forelse($sc['pages'] as $row)<tr><td class="max-w-xs truncate"><a href="{{ $row['key'] }}" target="_blank" class="hover:text-brand-600">{{ str_replace(rtrim(config('app.url'), '/'), '', $row['key']) }}</a></td><td class="text-right tabular-nums">{{ $row['clicks'] }}</td><td class="text-right tabular-nums">{{ number_format($row['impressions']) }}</td><td class="text-right tabular-nums">{{ $row['ctr'] }}%</td></tr>
                    @empty<tr><td colspan="4" class="py-6 text-center text-ink-500">No data yet.</td></tr>@endforelse
                    </tbody></table></section>
            </div>
            <p class="mt-3 text-xs text-ink-500">{{ $sc['start'] }} → {{ $sc['end'] }} (Search Console data lags about 2 days) · cached {{ \Illuminate\Support\Carbon::parse($sc['fetched_at'])->diffForHumans() }}</p>
        @endif
    @endif

    @if($tab === 'analytics')
        @if(! $gaReady)
            <div class="card p-6 text-sm">Enter the GA4 property ID in <a href="{{ route('admin.settings.edit', ['tab' => 'google']) }}" class="underline">Settings → Google</a> and grant <strong>{{ $clientEmail }}</strong> Viewer access on the property.</div>
        @elseif(isset($apiErrors['analytics']))
            <div class="rounded bg-red-50 px-4 py-3 text-sm text-red-700">Analytics: {{ $apiErrors['analytics'] }}</div>
        @elseif($ga)
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach([['Users', $ga['totals']['users']], ['Sessions', $ga['totals']['sessions']], ['Page views', $ga['totals']['pageviews']]] as [$l, $v])
                    <div class="card p-4"><p class="text-xs font-semibold uppercase text-ink-500">{{ $l }}</p><p class="text-3xl font-black">{{ number_format($v) }}</p></div>
                @endforeach
            </div>
            <section class="card mt-6 p-5"><h2 class="mb-2 font-bold">Users per day</h2><x-admin.bar-chart :series="array_map(fn ($r) => ['date' => substr($r['date'], 0, 4).'-'.substr($r['date'], 4, 2).'-'.substr($r['date'], 6, 2), 'value' => $r['activeUsers']], $ga['daily'])" label="users" color="#0d9488" /></section>
            <div class="mt-6 grid gap-6 xl:grid-cols-3">
                <section class="card overflow-x-auto xl:col-span-2"><h2 class="border-b border-ink-100 px-5 py-3 font-bold">Top pages</h2>
                    <table class="table-admin"><thead><tr><th>Page</th><th class="text-right">Views</th><th class="text-right">Users</th></tr></thead><tbody>
                    @foreach($ga['pages'] as $row)<tr><td class="max-w-md"><span class="block truncate font-medium">{{ $row['pageTitle'] }}</span><span class="block truncate text-xs text-ink-500">{{ $row['pagePath'] }}</span></td><td class="text-right tabular-nums">{{ number_format($row['screenPageViews']) }}</td><td class="text-right tabular-nums">{{ number_format($row['activeUsers']) }}</td></tr>@endforeach
                    </tbody></table></section>
                <div class="space-y-6">
                    <section class="card"><h2 class="border-b border-ink-100 px-5 py-3 font-bold">Sources</h2><ul class="divide-y divide-ink-100 text-sm">@foreach($ga['sources'] as $r)<li class="flex justify-between px-5 py-2"><span>{{ $r['sessionSource'] }}</span><span class="tabular-nums">{{ number_format($r['sessions']) }}</span></li>@endforeach</ul></section>
                    <section class="card"><h2 class="border-b border-ink-100 px-5 py-3 font-bold">Countries</h2><ul class="divide-y divide-ink-100 text-sm">@foreach($ga['countries'] as $r)<li class="flex justify-between px-5 py-2"><span>{{ $r['country'] }}</span><span class="tabular-nums">{{ number_format($r['activeUsers']) }}</span></li>@endforeach</ul></section>
                    <section class="card"><h2 class="border-b border-ink-100 px-5 py-3 font-bold">Devices</h2><ul class="divide-y divide-ink-100 text-sm">@foreach($ga['devices'] as $r)<li class="flex justify-between px-5 py-2"><span class="capitalize">{{ $r['deviceCategory'] }}</span><span class="tabular-nums">{{ number_format($r['activeUsers']) }}</span></li>@endforeach</ul></section>
                </div>
            </div>
        @endif
    @endif

    @if($tab === 'indexing')
        <div class="grid gap-4 sm:grid-cols-4">
            @foreach([['Indexed', $indexCounts['pass'], 'text-green-700'], ['Not indexed', $indexCounts['neutral'], 'text-yellow-700'], ['Errors', $indexCounts['fail'], 'text-red-700'], ['Never inspected', $indexCounts['unchecked'], 'text-ink-700']] as [$l, $v, $c])
                <div class="card p-4"><p class="text-xs font-semibold uppercase text-ink-500">{{ $l }}</p><p class="text-3xl font-black {{ $c }}">{{ $v }}</p></div>
            @endforeach
        </div>
        <div class="card mt-6 p-5">
            <h2 class="font-bold">Bulk submit</h2>
            <p class="mt-1 text-sm text-ink-700">Sends URLs to the Google Indexing API (quota 200/day) and IndexNow. Inspect individual posts from the post editor to refresh their index status.</p>
            <form method="post" action="{{ route('admin.google.bulk') }}" class="mt-3 flex flex-wrap gap-2">@csrf
                <button name="scope" value="recent" class="btn-primary">Published in last 48 h</button>
                <button name="scope" value="unsubmitted" class="btn-outline">Never submitted</button>
                <button name="scope" value="unindexed" class="btn-outline">Known not-indexed</button>
            </form>
        </div>
        @if($unindexed->isNotEmpty())
            <section class="card mt-6 overflow-x-auto"><h2 class="border-b border-ink-100 px-5 py-3 font-bold">Posts Google has not indexed</h2>
                <table class="table-admin"><thead><tr><th>Post</th><th>Coverage</th><th>Checked</th><th></th></tr></thead><tbody>
                @foreach($unindexed as $post)<tr><td><a href="{{ route('admin.posts.edit', $post) }}" class="font-medium hover:text-brand-600">{{ $post->title }}</a></td><td>{{ $post->index_coverage }}</td><td class="text-ink-500">{{ $post->index_checked_at?->diffForHumans() }}</td><td class="text-right"><form method="post" action="{{ route('admin.posts.index-request', $post) }}">@csrf<button class="text-xs font-semibold text-brand-600">Submit</button></form></td></tr>@endforeach
                </tbody></table></section>
        @endif
        <section class="card mt-6 overflow-x-auto"><h2 class="border-b border-ink-100 px-5 py-3 font-bold">Submission log</h2>
            <table class="table-admin"><thead><tr><th>When</th><th>Provider</th><th>Action</th><th>URL</th><th>Result</th></tr></thead><tbody>
            @forelse($logs as $log)<tr><td class="whitespace-nowrap text-ink-500">{{ $log->created_at->format('d M H:i') }}</td><td>{{ str_replace('_', ' ', $log->provider) }}</td><td>{{ $log->action }}</td><td class="max-w-xs truncate">{{ $log->post?->title ?? $log->url }}</td><td class="max-w-sm"><span class="{{ $log->status === 'ok' ? 'badge-green' : 'badge-red' }}">{{ $log->status }}</span> <span class="text-xs text-ink-500">{{ \Illuminate\Support\Str::limit($log->response, 120) }}</span></td></tr>
            @empty<tr><td colspan="5" class="py-6 text-center text-ink-500">Nothing submitted yet. Publishing a post submits it automatically.</td></tr>@endforelse
            </tbody></table>
            <div class="p-3">{{ $logs->links() }}</div>
        </section>
    @endif

    @if($tab === 'sitemaps')
        <div class="card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="font-bold">Sitemaps in Search Console</h2><p class="text-sm text-ink-700">Submit the main sitemap index and the Google News sitemap for <strong>{{ $siteUrl }}</strong>.</p></div>
                <form method="post" action="{{ route('admin.google.sitemaps') }}">@csrf<button class="btn-primary"><x-admin.icon name="send" class="h-4 w-4" /> Submit sitemaps now</button></form>
            </div>
            @if(isset($apiErrors['sitemaps']))<p class="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ $apiErrors['sitemaps'] }}</p>@endif
            <table class="table-admin mt-4"><thead><tr><th>Sitemap</th><th>Last submitted</th><th>Last read by Google</th><th>URLs</th><th>Errors / warnings</th></tr></thead><tbody>
                @forelse($sitemaps as $map)
                    <tr><td class="break-all"><a href="{{ $map['path'] }}" target="_blank" class="hover:text-brand-600">{{ $map['path'] }}</a></td><td class="text-ink-500">{{ isset($map['lastSubmitted']) ? \Illuminate\Support\Carbon::parse($map['lastSubmitted'])->diffForHumans() : '—' }}</td><td class="text-ink-500">{{ isset($map['lastDownloaded']) ? \Illuminate\Support\Carbon::parse($map['lastDownloaded'])->diffForHumans() : 'not yet' }}</td><td class="tabular-nums">{{ collect($map['contents'] ?? [])->sum('submitted') }} submitted · {{ collect($map['contents'] ?? [])->sum('indexed') }} indexed</td><td>{{ $map['errors'] ?? 0 }} / {{ $map['warnings'] ?? 0 }}</td></tr>
                @empty<tr><td colspan="5" class="py-6 text-center text-ink-500">No sitemaps found for this property yet.</td></tr>@endforelse
            </tbody></table>
        </div>
    @endif
@endif
@endsection
