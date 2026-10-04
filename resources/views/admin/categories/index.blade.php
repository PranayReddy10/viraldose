@extends('layouts.admin')
@section('title', 'Categories')
@section('actions')<a href="{{ route('admin.categories.create') }}" class="btn-primary">+ New category</a>@endsection
@section('content')
<div class="card overflow-x-auto">
    <table class="table-admin">
        <thead><tr><th>Name</th><th>Slug</th><th>Posts</th><th>Menu</th><th>Home</th><th>Active</th><th>Order</th><th></th></tr></thead>
        <tbody>
        @foreach($categories as $category)
            @foreach(collect([$category])->concat($category->children) as $c)
                <tr>
                    <td class="font-medium">{{ $c->parent_id ? '— ' : '' }}<span class="inline-block h-3 w-3 rounded-full align-middle mr-1" style="background: {{ $c->color }}"></span>{{ $c->name }}</td>
                    <td class="text-ink-500">/category/{{ $c->slug }}</td>
                    <td>{{ $c->posts_count }}</td>
                    <td>{{ $c->show_in_menu ? '✓' : '—' }}</td>
                    <td>{{ $c->show_on_home ? '✓' : '—' }}</td>
                    <td>{{ $c->is_active ? '✓' : '—' }}</td>
                    <td>{{ $c->sort_order }}</td>
                    <td class="text-right whitespace-nowrap"><a href="{{ route('admin.categories.edit', $c) }}" class="text-xs font-semibold text-brand-600 hover:underline">Edit</a> <x-admin.delete-button :action="route('admin.categories.destroy', $c)" class="ml-2" /></td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
</div>
@endsection
