@extends('layouts.admin')
@section('title', $reel->exists ? 'Edit Reel' : 'Add Reel')
@section('content')
<form method="post" action="{{ $reel->exists ? route('admin.reels.update', $reel) : route('admin.reels.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
    @csrf @if($reel->exists) @method('PUT') @endif
    <div class="card p-5 lg:col-span-2">
        <x-admin.field label="Title" name="title" :value="$reel->title" required :max="200" slug-source />
        <x-admin.field label="Slug" name="slug" :value="$reel->slug" slug-target />
        <x-admin.field label="Caption" name="caption" type="textarea" :rows="2" :value="$reel->caption" :max="1000" help="Shown over the video." />

        <h2 class="mb-2 mt-4 font-bold">Video source</h2>
        <div class="mb-3 grid gap-2 sm:grid-cols-2">
            @foreach(\App\Models\Reel::SOURCES as $key => $label)
                <label class="flex cursor-pointer items-center gap-2 rounded border border-ink-300 px-3 py-2 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                    <input type="radio" name="source_type" value="{{ $key }}" data-source-radio @checked(old('source_type', $reel->source_type) === $key)> {{ $label }}
                </label>
            @endforeach
        </div>
        <div data-source="upload" class="hidden">
            <label class="label">Video file (MP4 / MOV / WebM, up to 100 MB, vertical 9:16 recommended)</label>
            @if($reel->source_type === 'upload' && $reel->video_path)<p class="mb-1 text-xs text-ink-500">Current: <a href="{{ $reel->videoUrl() }}" target="_blank" class="underline">{{ basename($reel->video_path) }}</a></p>@endif
            <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm" class="mb-4 block w-full text-sm">
            @error('video')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div data-source="url" class="hidden">
            <x-admin.field label="Direct video URL" name="video_url" type="url" :value="$reel->source_type === 'url' ? $reel->video_path : ''" help="A public https://… .mp4 link (e.g. from DigitalOcean Spaces)." />
        </div>
        <div data-source="youtube" class="hidden">
            <x-admin.field label="YouTube Shorts / video URL" name="external_url" type="url" :value="$reel->source_type === 'youtube' ? $reel->external_url : ''" help="Thumbnail is fetched from YouTube automatically." />
        </div>
        <div data-source="instagram" class="hidden">
            <x-admin.field label="Instagram reel URL" name="external_url" type="url" :value="$reel->source_type === 'instagram' ? $reel->external_url : ''" help="https://www.instagram.com/reel/… — Instagram's embed is shown (upload a thumbnail for the home strip)." />
        </div>
    </div>
    <div class="space-y-6">
        <div class="card p-5">
            <h2 class="mb-3 font-bold">Thumbnail</h2>
            @if($reel->thumbnailUrl())<img src="{{ $reel->thumbnailUrl() }}" alt="" class="mb-2 w-32 rounded aspect-[9/16] object-cover">@endif
            <input type="file" name="thumbnail" accept="image/*" class="mb-2 block w-full text-sm">
            <x-admin.field label="…or thumbnail URL" name="thumbnail_url" type="url" :value="\Illuminate\Support\Str::startsWith($reel->thumbnail, 'http') ? $reel->thumbnail : ''" />
        </div>
        <div class="card p-5">
            <h2 class="mb-3 font-bold">Link &amp; publish</h2>
            <x-admin.select label="Related article" name="post_id" :value="$reel->post_id" :options="$posts->pluck('title', 'id')" placeholder="None" />
            <x-admin.select label="Category" name="category_id" :value="$reel->category_id" :options="$categories->mapWithKeys(fn ($c) => [$c->id => ($c->parent_id ? '— ' : '').$c->name])" placeholder="None" />
            <x-admin.field label="Publish date" name="published_at" type="datetime-local" :value="old('published_at', $reel->published_at?->format('Y-m-d\TH:i'))" />
            <x-admin.field label="Sort order" name="sort_order" type="number" :value="$reel->sort_order ?? 0" help="Lower numbers appear first; 0 = newest first." />
            <x-admin.checkbox label="Active (visible in feed)" name="is_active" :checked="$reel->is_active" />
            <button class="btn-primary mt-2 w-full" type="submit">Save reel</button>
        </div>
    </div>
</form>
@endsection
