@extends('layouts.admin')
@section('title', 'Subscribers')
@section('actions')<a href="{{ route('admin.subscribers.export') }}" class="btn-secondary">Export CSV</a>@endsection
@section('content')
<div class="card overflow-x-auto">
    <table class="table-admin">
        <thead><tr><th>Email</th><th>Status</th><th>Subscribed</th><th></th></tr></thead>
        <tbody>
        @forelse($subscribers as $s)
            <tr><td>{{ $s->email }}</td><td>{!! $s->is_active ? '<span class="badge-green">active</span>' : '<span class="badge-gray">unsubscribed</span>' !!}</td><td class="text-ink-500">{{ $s->created_at->format('d M Y') }}</td><td class="text-right"><x-admin.delete-button :action="route('admin.subscribers.destroy', $s)" /></td></tr>
        @empty<tr><td colspan="4" class="py-8 text-center text-ink-500">No subscribers yet.</td></tr>@endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $subscribers->links() }}</div>
@endsection
