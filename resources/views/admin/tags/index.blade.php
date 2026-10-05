@extends('layouts.admin')
@section('title', 'Tags')
@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card p-5">
        <h2 class="mb-3 font-bold">Add tag</h2>
        <form method="post" action="{{ route('admin.tags.store') }}">@csrf<x-admin.field label="Name" name="name" required :max="100" /><button class="btn-primary">Add</button></form>
    </div>
    <div class="card lg:col-span-2 overflow-x-auto">
        <form method="get" class="flex gap-2 border-b border-ink-100 p-3"><input type="search" name="q" value="{{ request('q') }}" placeholder="Search tags…" class="input"><button class="btn-secondary">Search</button></form>
        <table class="table-admin">
            <thead><tr><th>Name</th><th>Slug</th><th>Posts</th><th></th></tr></thead>
            <tbody>
            @foreach($tags as $tag)
                <tr>
                    <td><input name="name" value="{{ $tag->name }}" form="tag-{{ $tag->id }}" class="input !py-1"></td>
                    <td><input name="slug" value="{{ $tag->slug }}" form="tag-{{ $tag->id }}" class="input !py-1"></td>
                    <td>{{ $tag->posts_count }}</td>
                    <td class="whitespace-nowrap text-right"><button type="submit" form="tag-{{ $tag->id }}" class="text-xs font-semibold text-brand-600 hover:underline">Save</button> <button type="submit" form="tag-delete-{{ $tag->id }}" class="ml-2 text-xs font-semibold text-red-600 hover:underline">Delete</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="p-3">{{ $tags->links() }}</div>
        @foreach($tags as $tag)
            <form id="tag-{{ $tag->id }}" method="post" action="{{ route('admin.tags.update', $tag) }}" class="hidden">@csrf @method('PUT')</form>
            <form id="tag-delete-{{ $tag->id }}" method="post" action="{{ route('admin.tags.destroy', $tag) }}" class="hidden" data-confirm="Delete this tag?">@csrf @method('DELETE')</form>
        @endforeach
    </div>
</div>
@endsection
