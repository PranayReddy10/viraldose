@extends('layouts.admin')
@section('title', 'Ads')
@section('actions')<a href="{{ route('admin.ads.create') }}" class="btn-primary">+ New ad</a>@endsection
@section('content')
<div class="card overflow-x-auto">
    <table class="table-admin">
        <thead><tr><th>Name</th><th>Slot</th><th>Type</th><th>Active</th><th></th></tr></thead>
        <tbody>
        @forelse($ads as $ad)
            <tr>
                <td class="font-medium">{{ $ad->name }}</td>
                <td>{{ \App\Models\Ad::SLOTS[$ad->slot] ?? $ad->slot }}</td>
                <td>{{ $ad->code ? 'Code' : ($ad->image ? 'Image' : '—') }}</td>
                <td>{{ $ad->is_active ? '✓' : '—' }}</td>
                <td class="text-right whitespace-nowrap"><a href="{{ route('admin.ads.edit', $ad) }}" class="text-xs font-semibold text-brand-600 hover:underline">Edit</a> <x-admin.delete-button :action="route('admin.ads.destroy', $ad)" class="ml-2" /></td>
            </tr>
        @empty<tr><td colspan="5" class="py-8 text-center text-ink-500">No ads configured. Add AdSense or banner units per slot.</td></tr>@endforelse
        </tbody>
    </table>
</div>
@endsection
