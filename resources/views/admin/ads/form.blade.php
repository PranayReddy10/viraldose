@extends('layouts.admin')
@section('title', $ad->exists ? 'Edit ad' : 'New ad')
@section('content')
<form method="post" action="{{ $ad->exists ? route('admin.ads.update', $ad) : route('admin.ads.store') }}" enctype="multipart/form-data" class="card max-w-2xl p-5">
    @csrf @if($ad->exists) @method('PUT') @endif
    <x-admin.field label="Name" name="name" :value="$ad->name" required />
    <x-admin.select label="Slot (where it appears)" name="slot" :value="$ad->slot" :options="\App\Models\Ad::SLOTS" />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-admin.select label="Device" name="device" :value="$ad->device ?: 'all'" :options="\App\Models\Ad::DEVICES" />
        <x-admin.select label="Pages" name="pages" :value="$ad->pages ?: 'all'" :options="\App\Models\Ad::PAGES" />
    </div>
    <x-admin.field label="Ad code (AdSense / HTML)" name="code" type="textarea" :rows="6" :value="$ad->code" help="Paste the full ad unit code. Takes priority over the image." />
    <x-admin.file label="…or banner image" name="image" accept="image/*" button="Choose banner" :preview="$ad->image ? media_url($ad->image) : null" preview-class="!aspect-auto" help="PNG/JPG/GIF/WebP up to 2 MB, e.g. 728×90 or 300×250" />
    <x-admin.field label="Banner link URL" name="url" type="url" :value="$ad->url" />
    <x-admin.field label="Sort order" name="sort_order" type="number" :value="$ad->sort_order ?? 0" />
    <x-admin.checkbox label="Active" name="is_active" :checked="$ad->is_active" />
    <button class="btn-primary" type="submit">Save</button>
</form>
@endsection
