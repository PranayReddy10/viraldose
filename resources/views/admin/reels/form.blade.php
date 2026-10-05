@extends('layouts.admin')
@section('title', $reel->exists ? 'Edit Reel' : 'Add Reel')
@section('content')
<form method="post" action="{{ $reel->exists ? route('admin.reels.update', $reel) : route('admin.reels.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
    @csrf @if($reel->exists) @method('PUT') @endif
    <div class="card p-5 lg:col-span-2">
        <x-admin.field label="Title" name="title" :value="$reel->title" required :max="200" slug-source />
        <x-admin.field label="Slug" name="slug" :value="$reel->slug" slug-target />
        <x-admin.field label="Caption" name="caption" type="textarea" :rows="2" :value="$reel->caption" :max="1000" help="Shown over the video." />

        <h2 class="mb-2 mt-4 font-bold">Source</h2>
        <div class="mb-3 grid gap-2 sm:grid-cols-2">
            @foreach(\App\Models\Reel::SOURCES as $key => $label)
                <label class="flex cursor-pointer items-center gap-2 rounded border border-ink-300 px-3 py-2 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                    <input type="radio" name="source_type" value="{{ $key }}" data-source-radio @checked(old('source_type', $reel->source_type) === $key)> {{ $label }}
                </label>
            @endforeach
        </div>
        <div data-source="upload" class="hidden">
            @if($reel->source_type === 'upload' && $reel->video_path)<p class="mb-1 text-xs text-ink-500">Current video: <a href="{{ $reel->videoUrl() }}" target="_blank" class="underline">{{ basename($reel->video_path) }}</a></p>@endif
            <x-admin.file label="Video file" name="video" accept="video/mp4,video/quicktime,video/webm" button="Choose video" icon="reels" help="MP4 / MOV / WebM up to 100 MB · vertical 9:16 recommended" />
        </div>
        <div data-source="image" class="hidden">
            <x-admin.file label="Photo" name="image" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" button="Choose photo" :preview="$reel->isImage() ? $reel->imageUrl() : null" preview-class="!w-40 !mx-auto aspect-[9/16]" help="JPG / PNG / WebP up to 8 MB · vertical 1080×1920 looks best. Shown full-screen in the reels feed and can be posted to Instagram as a photo." />
            <x-admin.field label="…or photo URL" name="image_url" type="url" :value="$reel->isImage() && \Illuminate\Support\Str::startsWith($reel->thumbnail, 'http') ? $reel->thumbnail : ''" />
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
        <div class="card p-5" data-source-hide="image">
            <h2 class="mb-3 font-bold">Thumbnail</h2>
            <x-admin.file name="thumbnail" accept="image/*" button="Choose thumbnail" :preview="$reel->thumbnailUrl()" preview-class="!w-28 !mx-auto aspect-[9/16]" help="Vertical 1080×1920 works best" />
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
        @if($reel->exists)
        <div class="card p-5">
            <h2 class="mb-1 flex items-center gap-2 font-bold"><svg class="h-5 w-5" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" fill="#e1306c"/><circle cx="12" cy="12" r="4.5" fill="none" stroke="#fff" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.3" fill="#fff"/></svg> Instagram</h2>
            @if($reel->canShareToInstagram())
                <p class="mb-2 text-xs text-ink-500">{{ $reel->isImage() ? 'Posts the photo to the feed as a 4:5 JPEG.' : 'Posts the video as a Reel (needs a public .mp4 link).' }}</p>
                <label class="label" for="reel-ig-caption">Caption</label>
                <textarea id="reel-ig-caption" name="caption" form="form-reel-instagram" rows="4" class="input text-xs">{{ old('caption', app(\App\Services\InstagramPublisher::class)->caption($reel)) }}</textarea>
                @error('instagram')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                <div class="mt-3 flex flex-wrap gap-2">
                    @if(app(\App\Services\InstagramPublisher::class)->isReady())
                        <button type="submit" form="form-reel-instagram" class="btn bg-[#e1306c] text-white hover:bg-[#c1275a] !px-3 !py-1.5 text-xs">{{ $reel->isImage() ? 'Post photo to Instagram' : 'Post as Instagram Reel' }}</button>
                    @else
                        <a href="{{ route('admin.settings.edit', ['tab' => 'instagram']) }}" class="btn-outline !px-3 !py-1.5 text-xs">Connect Instagram for one-click posting</a>
                    @endif
                    <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs" data-copy="#reel-ig-caption">Copy caption</button>
                </div>
            @else
                <p class="text-xs text-ink-500">Only uploaded videos, direct .mp4 links and photos can be posted from here. YouTube / Instagram embeds cannot be re-posted.</p>
            @endif
            @if(($shares ?? collect())->isNotEmpty())
                <ul class="mt-3 divide-y divide-ink-100 border-t border-ink-100 text-xs">
                    @foreach($shares as $share)
                        <li class="flex items-center gap-2 py-1.5">
                            <span class="{{ ['published' => 'badge-green', 'processing' => 'badge-blue', 'failed' => 'badge-red'][$share->status] ?? 'badge-gray' }}">{{ $share->status }}</span>
                            <span class="min-w-0 flex-1 truncate text-ink-500" title="{{ $share->response }}">{{ $share->media_type }} · {{ $share->created_at->diffForHumans() }}@if($share->status === 'failed') · {{ \Illuminate\Support\Str::limit($share->response, 80) }}@endif</span>
                            @if($share->permalink)<a href="{{ $share->permalink }}" target="_blank" class="text-brand-600 underline">open</a>@endif
                            @if($share->status === 'processing')<button type="submit" form="form-share-check-{{ $share->id }}" class="text-brand-600 underline">check</button>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        @endif
    </div>
</form>
@if($reel->exists)
    <form id="form-reel-instagram" method="post" action="{{ route('admin.reels.share.instagram', $reel) }}" class="hidden" data-confirm="Post this to Instagram now?">@csrf</form>
    @foreach(($shares ?? collect()) as $share)<form id="form-share-check-{{ $share->id }}" method="post" action="{{ route('admin.shares.check', $share) }}" class="hidden">@csrf</form>@endforeach
@endif
@endsection
