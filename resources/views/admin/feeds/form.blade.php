@extends('layouts.admin')
@section('title', $feed->exists ? 'Edit Feed' : 'Add Feed')
@section('content')
<form method="post" action="{{ $feed->exists ? route('admin.feeds.update', $feed) : route('admin.feeds.store') }}" class="grid gap-6 lg:grid-cols-3">
    @csrf @if($feed->exists) @method('PUT') @endif
    <div class="card p-5 lg:col-span-2">
        <x-admin.field label="Feed name (shown as source)" name="name" :value="$feed->name" required />
        <x-admin.field label="Feed URL" name="url" type="url" :value="$feed->url" required help="RSS 2.0 or Atom." />
        <button type="button" class="btn-outline mb-4" data-feed-preview="{{ route('admin.feeds.preview') }}">Preview feed</button>
        <ul id="feed-preview" class="mb-4 hidden divide-y divide-ink-100 rounded border border-ink-100 text-sm"></ul>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.select label="Import into category" name="category_id" :value="$feed->category_id" :options="$categories->mapWithKeys(fn ($c) => [$c->id => ($c->parent_id ? '— ' : '').$c->name])" placeholder="Select a category" />
            <x-admin.select label="Author" name="user_id" :value="$feed->user_id ?? auth()->id()" :options="$authors->pluck('name', 'id')" />
            <x-admin.select label="Language" name="language" :value="$feed->language" :options="$languages" />
            <x-admin.field label="Max items per fetch" name="max_items" type="number" :value="$feed->max_items" />
        </div>
    </div>
    <div class="card space-y-3 p-5">
        <x-admin.checkbox label="Auto-publish imported items" name="auto_publish" :checked="$feed->auto_publish" help="Off = save as drafts for editing (recommended for SEO)." />
        <x-admin.checkbox label="Import featured image from feed" name="import_images" :checked="$feed->import_images" />
        <x-admin.checkbox label="Fetch the full article from the source page" name="fetch_full_content" :checked="$feed->fetch_full_content ?? true" help="Most feeds only carry a teaser (one linked image + a line). When on, the story page is downloaded and the article body, with its images, is imported instead." />
        <x-admin.checkbox label="Active" name="is_active" :checked="$feed->is_active" />
        <button class="btn-primary w-full" type="submit">Save feed</button>
    </div>
</form>
@endsection
