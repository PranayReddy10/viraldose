@extends('layouts.admin')
@section('title', $ad->exists ? 'Edit ad' : 'New ad')
@section('content')
<form method="post" action="{{ $ad->exists ? route('admin.ads.update', $ad) : route('admin.ads.store') }}" enctype="multipart/form-data" class="card max-w-2xl p-5">
    @csrf @if($ad->exists) @method('PUT') @endif
    <x-admin.field label="Name" name="name" :value="$ad->name" required />
    <x-admin.select label="Slot" name="slot" :value="$ad->slot" :options="\App\Models\Ad::SLOTS" />
    <x-admin.field label="Ad code (AdSense / HTML)" name="code" type="textarea" :rows="6" :value="$ad->code" help="Paste the full ad unit code. Takes priority over the image." />
    <div class="mb-4"><label class="label">…or banner image</label>@if($ad->image)<img src="{{ media_url($ad->image) }}" alt="" class="mb-2 max-h-32">@endif<input type="file" name="image" accept="image/*" class="block w-full text-sm"></div>
    <x-admin.field label="Banner link URL" name="url" type="url" :value="$ad->url" />
    <x-admin.field label="Sort order" name="sort_order" type="number" :value="$ad->sort_order ?? 0" />
    <x-admin.checkbox label="Active" name="is_active" :checked="$ad->is_active" />
    <button class="btn-primary" type="submit">Save</button>
</form>
@endsection
