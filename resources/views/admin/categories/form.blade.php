@extends('layouts.admin')
@section('title', $category->exists ? 'Edit category' : 'New category')
@section('content')
<form method="post" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="grid gap-6 lg:grid-cols-3">
    @csrf @if($category->exists) @method('PUT') @endif
    <div class="card p-5 lg:col-span-2">
        <x-admin.field label="Name" name="name" :value="$category->name" required :max="120" slug-source />
        <x-admin.field label="Slug" name="slug" :value="$category->slug" slug-target />
        <x-admin.select label="Parent category" name="parent_id" :value="$category->parent_id" :options="$parents->pluck('name', 'id')" placeholder="None (top level)" />
        <x-admin.field label="Description" name="description" type="textarea" :value="$category->description" help="Shown at the top of the category page. Helps the page rank for the topic." />
        <h2 class="mb-3 mt-6 font-bold">SEO</h2>
        <x-admin.field label="Meta title" name="meta_title" :value="$category->meta_title" :max="60" counter />
        <x-admin.field label="Meta description" name="meta_description" type="textarea" :rows="2" :value="$category->meta_description" :max="160" counter />
    </div>
    <div class="card p-5 space-y-4">
        <div><label class="label" for="f-color">Colour</label><input id="f-color" type="color" name="color" value="{{ old('color', $category->color ?: '#dc2626') }}" class="h-10 w-20"></div>
        <x-admin.field label="Sort order" name="sort_order" type="number" :value="$category->sort_order ?? 0" />
        <x-admin.checkbox label="Show in navigation menu" name="show_in_menu" :checked="$category->show_in_menu" />
        <x-admin.checkbox label="Show section on home page" name="show_on_home" :checked="$category->show_on_home" />
        <x-admin.checkbox label="Active" name="is_active" :checked="$category->is_active" />
        <button class="btn-primary w-full" type="submit">Save</button>
    </div>
</form>
@endsection
