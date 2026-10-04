@extends('layouts.admin')
@section('title', 'Redirects')
@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6">
        <form method="post" action="{{ route('admin.redirects.store') }}" class="card p-5">
            @csrf
            <h2 class="mb-3 font-bold">Add redirect</h2>
            <x-admin.field label="From path" name="from_path" placeholder="/old-url" help="Path on this domain, e.g. /2024/05/old-slug.html" required />
            <x-admin.field label="To URL or path" name="to_path" required help="/news/new-slug or a full URL" />
            <x-admin.select label="Status" name="status_code" value="301" :options="[301 => '301 Permanent', 302 => '302 Temporary', 308 => '308 Permanent (keep method)', 307 => '307 Temporary (keep method)']" />
            <button class="btn-primary">Save</button>
        </form>
        <form method="post" action="{{ route('admin.redirects.import') }}" enctype="multipart/form-data" class="card p-5">
            @csrf
            <h2 class="mb-1 font-bold">Import CSV</h2>
            <p class="mb-3 text-xs text-ink-500">Columns: from, to, status (optional). One rule per line.</p>
            <input type="file" name="file" accept=".csv,.txt" required class="mb-3 block w-full text-sm">
            <button class="btn-secondary">Import</button>
        </form>
    </div>
    <div class="card lg:col-span-2 overflow-x-auto">
        <form method="get" class="flex gap-2 border-b border-ink-100 p-3"><input type="search" name="q" value="{{ request('q') }}" placeholder="Search…" class="input"><button class="btn-secondary">Search</button></form>
        <table class="table-admin">
            <thead><tr><th>From</th><th>To</th><th>Code</th><th>Hits</th><th></th></tr></thead>
            <tbody>
            @forelse($redirects as $r)
                <tr><td class="font-mono text-xs break-all">{{ $r->from_path }}</td><td class="font-mono text-xs break-all">{{ $r->to_path }}</td><td>{{ $r->status_code }}</td><td>{{ $r->hits }}</td><td class="text-right"><x-admin.delete-button :action="route('admin.redirects.destroy', $r)" /></td></tr>
            @empty<tr><td colspan="5" class="py-8 text-center text-ink-500">No redirects yet. Old URLs from the previous site are matched here before a 404 is shown.</td></tr>@endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $redirects->links() }}</div>
    </div>
</div>
@endsection
