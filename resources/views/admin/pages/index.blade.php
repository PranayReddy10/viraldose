@extends('layouts.admin')
@section('title', 'Pages')
@section('actions')<a href="{{ route('admin.pages.create') }}" class="btn-primary">+ New page</a>@endsection
@section('content')
<div class="card overflow-x-auto">
    <table class="table-admin">
        <thead><tr><th>Title</th><th>URL</th><th>Footer</th><th>Active</th><th>Updated</th><th></th></tr></thead>
        <tbody>
        @forelse($pages as $page)
            <tr>
                <td class="font-medium">{{ $page->title }}</td>
                <td><a href="{{ $page->url() }}" target="_blank" class="text-ink-500 hover:text-brand-600">/page/{{ $page->slug }}</a></td>
                <td>{{ $page->show_in_footer ? '✓' : '—' }}</td>
                <td>{{ $page->is_active ? '✓' : '—' }}</td>
                <td class="text-ink-500">{{ $page->updated_at->diffForHumans() }}</td>
                <td class="text-right whitespace-nowrap"><a href="{{ route('admin.pages.edit', $page) }}" class="text-xs font-semibold text-brand-600 hover:underline">Edit</a> <x-admin.delete-button :action="route('admin.pages.destroy', $page)" class="ml-2" /></td>
            </tr>
        @empty<tr><td colspan="6" class="py-8 text-center text-ink-500">No pages yet.</td></tr>@endforelse
        </tbody>
    </table>
</div>
@endsection
