@extends('layouts.admin')
@section('title', $user->exists ? 'Edit user' : 'New user')
@section('content')
<form method="post" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
    @csrf @if($user->exists) @method('PUT') @endif
    <div class="card p-5 lg:col-span-2">
        <x-admin.field label="Name" name="name" :value="$user->name" required />
        <x-admin.field label="Email" name="email" type="email" :value="$user->email" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field :label="$user->exists ? 'New password (leave blank to keep)' : 'Password'" name="password" type="password" :required="! $user->exists" />
            <x-admin.field label="Confirm password" name="password_confirmation" type="password" />
        </div>
        <x-admin.field label="Bio" name="bio" type="textarea" :value="$user->bio" help="Shown on the author page and under articles (E-E-A-T)." />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field label="Website" name="website" type="url" :value="$user->website" />
            <x-admin.field label="X / Twitter" name="twitter" :value="$user->twitter" />
        </div>
    </div>
    <div class="card p-5 space-y-4">
        <x-admin.select label="Role" name="role" :value="$user->role" :options="['admin' => 'Admin – full access', 'editor' => 'Editor – content & moderation', 'author' => 'Author – own posts only']" />
        <x-admin.file label="Avatar" name="avatar" accept="image/*" button="Choose photo" :preview="$user->avatarUrl()" preview-class="!h-24 !w-24 !mx-auto rounded-full aspect-square" help="Square image, up to 2 MB" />
        <x-admin.checkbox label="Active" name="is_active" :checked="$user->is_active" />
        <button class="btn-primary w-full" type="submit">Save</button>
    </div>
</form>
@endsection
