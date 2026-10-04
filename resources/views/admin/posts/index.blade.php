@extends('layouts.admin')
@section('title', 'Posts')
@section('actions')<a href="{{ route('admin.posts.create') }}" class="btn-primary">+ New post</a>@endsection
@section('content')
<form method="get" class="mb-4 flex flex-wrap gap-2">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search title…" class="input !w-auto flex-1 min-w-40">
    <select name="status" class="input !w-auto">
        <option value="">All statuses</option>
        @foreach(['published' => 'Published', 'scheduled' => 'Scheduled', 'draft' => 'Draft', 'archived' => 'Archived'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
    </select>
    <select name="category" class="input !w-auto">
        <option value="">All categories</option>
        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->name }}</option>@endforeach
    </select>
    <label class="flex items-center gap-1 text-sm"><input type="checkbox" name="trashed" value="1" @checked(request('trashed'))> Trash</label>
    <button class="btn-secondary">Filter</button>
</form>

<form method="post" action="{{ route('admin.posts.bulk') }}" class="card overflow-x-auto">
    @csrf
    <div class="flex items-center gap-2 border-b border-ink-100 px-3 py-2 text-sm">
        <select name="action" class="input !w-auto !py-1">
            <option value="publish">Publish</option><option value="draft">Move to draft</option><option value="feature">Mark featured</option><option value="unfeature">Remove featured</option><option value="trash">Move to trash</option>
        </select>
        <button class="btn-secondary !py-1">Apply to selected</button>
        <span class="ml-auto text-ink-500">{{ $posts->total() }} posts</span>
    </div>
    <table class="table-admin">
        <thead><tr><th><input type="checkbox" id="select-all"></th><th>Title</th><th>Category</th><th>Author</th><th>Status</th><th>Views</th><th>Date</th><th></th></tr></thead>
        <tbody>
        @forelse($posts as $post)
            <tr>
                <td><input type="checkbox" name="ids[]" value="{{ $post->id }}"></td>
                <td class="max-w-md">
                    <a href="{{ route('admin.posts.edit', $post) }}" class="font-medium hover:text-brand-600">{{ $post->title }}</a>
                    <div class="mt-0.5 flex flex-wrap gap-1 text-[10px] uppercase font-bold text-ink-500">
                        @if($post->is_slider)<span>Slider</span>@endif @if($post->is_featured)<span>Featured</span>@endif @if($post->is_breaking)<span class="text-brand-600">Breaking</span>@endif @if($post->noindex)<span class="text-red-600">Noindex</span>@endif
                    </div>
                </td>
                <td>{{ $post->category?->name }}</td>
                <td>{{ $post->author?->name }}</td>
                <td>@include('admin.posts._status')</td>
                <td>{{ number_format($post->views) }}</td>
                <td class="whitespace-nowrap text-ink-500">{{ $post->published_at?->format('d M Y H:i') ?? '—' }}</td>
                <td class="whitespace-nowrap text-right">
                    @if($post->trashed())
                        <button formaction="{{ route('admin.posts.restore', $post->id) }}" formmethod="post" class="text-xs font-semibold text-green-700 hover:underline">Restore</button>
                    @else
                        <a href="{{ $post->url() }}{{ $post->isPublished() ? '' : '?preview=1' }}" target="_blank" class="text-xs font-semibold text-ink-500 hover:underline">View</a>
                        <a href="{{ route('admin.posts.edit', $post) }}" class="ml-2 text-xs font-semibold text-brand-600 hover:underline">Edit</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="py-10 text-center text-ink-500">No posts found.</td></tr>
        @endforelse
        </tbody>
    </table>
</form>
@if(request('trashed') && auth()->user()->isAdmin())
    <div class="mt-3 flex flex-wrap gap-3">
        @foreach($posts as $post)
            <x-admin.delete-button :action="route('admin.posts.force', $post->id)" :label="'Delete forever: '.\Illuminate\Support\Str::limit($post->title, 30)" confirm="Permanently delete this post and its images?" />
        @endforeach
    </div>
@endif
<div class="mt-4">{{ $posts->links() }}</div>
@endsection
