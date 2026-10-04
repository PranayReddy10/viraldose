@extends('layouts.admin')
@section('title', $post->exists ? 'Edit post' : 'New post')
@section('actions')
    @if($post->exists)<a href="{{ $post->url() }}{{ $post->isPublished() ? '' : '?preview=1' }}" target="_blank" class="btn-outline">{{ $post->isPublished() ? 'View' : 'Preview' }}</a>@endif
@endsection
@section('content')
@php
    $catOptions = [];
    foreach ($categories as $c) { $catOptions[$c->id] = $c->name; foreach ($c->children as $ch) { $catOptions[$ch->id] = '— '.$ch->name; } }
    $user = auth()->user();
@endphp
<form method="post" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-3">
    @csrf @if($post->exists) @method('PUT') @endif

    <div class="xl:col-span-2 space-y-6">
        <div class="card p-5">
            <x-admin.field label="Title" name="title" :value="$post->title" required :max="200" counter slug-source />
            <x-admin.field label="Slug" name="slug" :value="$post->slug" help="Lowercase letters, numbers and dashes. Changing a published slug breaks old links — add a redirect." slug-target />
            <x-admin.field label="Excerpt" name="excerpt" type="textarea" :value="$post->excerpt" :max="500" counter help="Short summary shown in listings and used as the default meta description." />
            <label class="label">Content</label>
            <div id="editor" data-upload-url="{{ route('admin.media.upload') }}" class="bg-white"></div>
            <textarea id="content" name="content" class="hidden">{{ old('content', $post->content) }}</textarea>
            @error('content')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="card p-5">
            <h2 class="mb-4 font-bold">SEO</h2>
            <div class="mb-5 rounded border border-ink-100 bg-ink-100/40 p-4">
                <p class="text-xs text-ink-500 mb-1">Google preview</p>
                <p id="pv-title" class="text-[#1a0dab] text-lg leading-tight truncate">Title</p>
                <p id="pv-url" class="text-[#006621] text-sm truncate"></p>
                <p id="pv-desc" class="text-sm text-ink-700 line-clamp-2"></p>
            </div>
            <x-admin.field label="Meta title" name="meta_title" :value="$post->meta_title" :max="60" counter help="Leave blank to use the post title. Aim for 50–60 characters." />
            <x-admin.field label="Meta description" name="meta_description" type="textarea" :rows="2" :value="$post->meta_description" :max="160" counter help="Aim for 120–160 characters. Falls back to the excerpt." />
            <x-admin.field label="Focus keywords" name="meta_keywords" :value="$post->meta_keywords" help="Comma separated. Used for internal search relevance." />
            <x-admin.field label="Canonical URL" name="canonical_url" type="url" :value="$post->canonical_url" help="Only if this article was first published elsewhere." />
            <x-admin.checkbox label="Noindex this post" name="noindex" :checked="$post->noindex" help="Hide from search engines and sitemaps." />
        </div>

        <div class="card p-5">
            <h2 class="mb-4 font-bold">Source attribution (optional)</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field label="Source name" name="source_name" :value="$post->source_name" />
                <x-admin.field label="Source URL" name="source_url" type="url" :value="$post->source_url" />
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card p-5">
            <h2 class="mb-4 font-bold">Publish</h2>
            <x-admin.select label="Status" name="status" :value="$post->status" :options="['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']" />
            <x-admin.field label="Publish date" name="published_at" type="datetime-local" :value="old('published_at', $post->published_at?->format('Y-m-d\TH:i'))" help="Set a future date to schedule. Leave blank to publish immediately." />
            @if($user->canManageAllPosts())
                <x-admin.select label="Author" name="user_id" :value="$post->user_id ?? $user->id" :options="$authors->pluck('name', 'id')" />
            @endif
            <div class="flex gap-2">
                <button class="btn-primary flex-1" type="submit">{{ $post->exists ? 'Save changes' : 'Create post' }}</button>
                @if($post->exists)<x-admin.delete-button :action="route('admin.posts.destroy', $post)" label="Trash" confirm="Move this post to trash?" class="btn-outline !text-red-600" />@endif
            </div>
        </div>

        <div class="card p-5">
            <h2 class="mb-4 font-bold">Category &amp; tags</h2>
            <x-admin.select label="Category" name="category_id" :value="$post->category_id" :options="$catOptions" placeholder="Select category" />
            <x-admin.field label="Tags" name="tags" :value="$tagString" help="Comma separated, e.g. cricket, ipl 2026" />
        </div>

        <div class="card p-5">
            <h2 class="mb-4 font-bold">Featured image</h2>
            <img id="image-preview" src="{{ $post->imageUrl('medium') ?: '' }}" alt="" class="mb-3 w-full rounded aspect-video object-cover {{ $post->image ? '' : 'hidden' }}">
            <input type="file" name="image" accept="image/*" data-preview="image-preview" class="block w-full text-sm">
            <p class="mt-1 text-xs text-ink-500">JPG/PNG/WebP up to 5 MB. 1200×675 recommended. WebP variants are generated automatically.</p>
            <x-admin.field label="…or image URL" name="image_url" type="url" :value="\Illuminate\Support\Str::startsWith($post->image, 'http') ? $post->image : ''" class="mt-3" />
            <x-admin.field label="Alt text" name="image_alt" :value="$post->image_alt" :max="200" help="Describe the image for accessibility and image search." />
            <x-admin.field label="Caption" name="image_caption" :value="$post->image_caption" :max="300" />
            @if($post->image)<x-admin.checkbox label="Remove current image" name="remove_image" />@endif
        </div>

        <div class="card p-5">
            <h2 class="mb-4 font-bold">Options</h2>
            @if($user->canManageAllPosts())
                <x-admin.checkbox label="Show in home slider" name="is_slider" :checked="$post->is_slider" />
                <x-admin.checkbox label="Featured" name="is_featured" :checked="$post->is_featured" />
                <x-admin.checkbox label="Breaking news" name="is_breaking" :checked="$post->is_breaking" help="Appears in the ticker under the header." />
                <x-admin.checkbox label="Recommended" name="is_recommended" :checked="$post->is_recommended" />
            @endif
            <x-admin.checkbox label="Allow comments" name="allow_comments" :checked="$post->allow_comments" />
        </div>
    </div>
</form>
@endsection
