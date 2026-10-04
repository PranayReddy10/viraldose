@extends('layouts.admin')
@section('title', 'Messages')
@section('content')
<div class="card overflow-x-auto">
    <table class="table-admin">
        <thead><tr><th>From</th><th>Subject</th><th>Received</th><th></th></tr></thead>
        <tbody>
        @forelse($messages as $m)
            <tr class="{{ $m->is_read ? '' : 'font-semibold' }}">
                <td>{{ $m->name }}<span class="block text-xs text-ink-500 font-normal">{{ $m->email }}</span></td>
                <td><a href="{{ route('admin.messages.show', $m) }}" class="hover:text-brand-600">{{ $m->subject ?: '(no subject)' }}</a></td>
                <td class="text-ink-500 font-normal">{{ $m->created_at->diffForHumans() }}</td>
                <td class="text-right"><x-admin.delete-button :action="route('admin.messages.destroy', $m)" /></td>
            </tr>
        @empty<tr><td colspan="4" class="py-8 text-center text-ink-500">Inbox empty.</td></tr>@endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $messages->links() }}</div>
@endsection
