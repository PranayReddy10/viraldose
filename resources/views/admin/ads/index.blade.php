@extends('layouts.admin')
@section('title', 'Ads')
@section('actions')<a href="{{ route('admin.ads.create') }}" class="btn-primary">+ New ad</a>@endsection
@section('content')
<div class="mb-4 card p-4 text-sm">
    <p class="font-semibold">Ad slots available across the site</p>
    <ul class="mt-2 grid gap-x-6 gap-y-1 text-xs text-ink-700 sm:grid-cols-2 lg:grid-cols-3">
        @foreach(\App\Models\Ad::SLOTS as $key => $label)<li><code class="text-brand-600">{{ $key }}</code> – {{ $label }}</li>@endforeach
    </ul>
    <p class="mt-2 text-xs text-ink-500">Paste an AdSense unit (or any HTML) per slot, or upload a banner image with a link. Each ad can target mobile/desktop and specific page types. Site-wide switches (AdSense Auto Ads, sticky mobile ad) are in <a href="{{ route('admin.settings.edit', ['tab' => 'ads']) }}" class="underline">Settings → Ads</a>.</p>
</div>
<div class="card overflow-x-auto">
    <table class="table-admin">
        <thead><tr><th>Name</th><th>Slot</th><th>Device</th><th>Pages</th><th>Type</th><th>Active</th><th></th></tr></thead>
        <tbody>
        @forelse($ads as $ad)
            <tr>
                <td class="font-medium">{{ $ad->name }}</td>
                <td class="max-w-xs">{{ \App\Models\Ad::SLOTS[$ad->slot] ?? $ad->slot }}</td>
                <td>{{ \App\Models\Ad::DEVICES[$ad->device] ?? $ad->device }}</td>
                <td>{{ \App\Models\Ad::PAGES[$ad->pages] ?? $ad->pages }}</td>
                <td>{{ $ad->code ? 'Code' : ($ad->image ? 'Image' : '—') }}</td>
                <td>{{ $ad->is_active ? '✓' : '—' }}</td>
                <td class="text-right whitespace-nowrap"><a href="{{ route('admin.ads.edit', $ad) }}" class="text-xs font-semibold text-brand-600 hover:underline">Edit</a> <x-admin.delete-button :action="route('admin.ads.destroy', $ad)" class="ml-2" /></td>
            </tr>
        @empty<tr><td colspan="7" class="py-8 text-center text-ink-500">No ads configured. Add AdSense or banner units per slot.</td></tr>@endforelse
        </tbody>
    </table>
</div>
@endsection
