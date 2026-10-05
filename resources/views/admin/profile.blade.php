@extends('layouts.admin')
@section('title', 'My profile')
@section('content')
<form method="post" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data" class="card max-w-2xl p-5">
    @csrf @method('PUT')
    <x-admin.field label="Name" name="name" :value="$user->name" required />
    <x-admin.field label="Email" name="email" type="email" :value="$user->email" required />
    <x-admin.field label="Bio" name="bio" type="textarea" :value="$user->bio" />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-admin.field label="Website" name="website" type="url" :value="$user->website" />
        <x-admin.field label="X / Twitter" name="twitter" :value="$user->twitter" />
    </div>
    <x-admin.file label="Avatar" name="avatar" accept="image/*" button="Choose photo" :preview="$user->avatarUrl()" preview-class="!h-24 !w-24 !mx-auto rounded-full aspect-square" help="Square image, up to 2 MB" />
    <h2 class="mb-3 mt-6 font-bold">Change password</h2>
    <x-admin.field label="Current password" name="current_password" type="password" />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-admin.field label="New password" name="password" type="password" />
        <x-admin.field label="Confirm new password" name="password_confirmation" type="password" />
    </div>
    <button class="btn-primary" type="submit">Save profile</button>
</form>
@endsection
