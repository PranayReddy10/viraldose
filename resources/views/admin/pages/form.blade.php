@extends('layouts.admin')
@section('title', $page->exists ? 'Edit page' : 'New page')
@section('content')
<form method="post" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="grid gap-6 lg:grid-cols-3">
    @csrf @if($page->exists) @method('PUT') @endif
    <div class="card p-5 lg:col-span-2">
        <x-admin.field label="Title" name="title" :value="$page->title" required :max="200" slug-source />
        <x-admin.field label="Slug" name="slug" :value="$page->slug" slug-target />
        <label class="label">Content</label>
        <div id="editor" data-upload-url="{{ route('admin.media.upload') }}" class="bg-white"></div>
        <textarea id="content" name="content" class="hidden">{{ old('content', $page->content) }}</textarea>
        <h2 class="mb-3 mt-6 font-bold">SEO</h2>
        <x-admin.field label="Meta title" name="meta_title" :value="$page->meta_title" :max="60" counter />
        <x-admin.field label="Meta description" name="meta_description" type="textarea" :rows="2" :value="$page->meta_description" :max="160" counter />
    </div>
    <div class="card p-5 space-y-4">
        <x-admin.field label="Sort order" name="sort_order" type="number" :value="$page->sort_order ?? 0" />
        <x-admin.checkbox label="Active" name="is_active" :checked="$page->is_active" />
        <x-admin.checkbox label="Show in footer" name="show_in_footer" :checked="$page->show_in_footer" />
        <x-admin.checkbox label="Noindex" name="noindex" :checked="$page->noindex" />
        <button class="btn-primary w-full" type="submit">Save</button>
    </div>
</form>
@endsection
