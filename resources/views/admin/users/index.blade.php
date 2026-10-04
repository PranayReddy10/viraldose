@extends('layouts.admin')
@section('title', 'Users')
@section('actions')<a href="{{ route('admin.users.create') }}" class="btn-primary">+ New user</a>@endsection
@section('content')
<div class="card overflow-x-auto">
    <table class="table-admin">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Posts</th><th>Active</th><th></th></tr></thead>
        <tbody>
        @foreach($users as $u)
            <tr>
                <td class="font-medium">{{ $u->name }}<span class="block text-xs text-ink-500">/author/{{ $u->slug }}</span></td>
                <td>{{ $u->email }}</td>
                <td class="capitalize">{{ $u->role }}</td>
                <td>{{ $u->posts_count }}</td>
                <td>{{ $u->is_active ? '✓' : '—' }}</td>
                <td class="text-right whitespace-nowrap"><a href="{{ route('admin.users.edit', $u) }}" class="text-xs font-semibold text-brand-600 hover:underline">Edit</a> @if($u->id !== auth()->id())<x-admin.delete-button :action="route('admin.users.destroy', $u)" class="ml-2" />@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
