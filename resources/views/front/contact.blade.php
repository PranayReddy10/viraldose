@extends('layouts.app')
@section('content')
<div class="container-site py-6">
    <x-breadcrumbs />
    <div class="grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Contact Us</h1>
            <p class="mt-2 text-ink-700">Have a tip, correction or partnership enquiry? Write to us
                @if(setting('contact_email')) at <a href="mailto:{{ setting('contact_email') }}" class="text-brand-600 underline">{{ setting('contact_email') }}</a> or @endif use the form below.</p>
            @if(setting('contact_address'))<p class="mt-2 text-sm text-ink-500">{{ setting('contact_address') }}</p>@endif
            <form action="{{ route('contact.send') }}" method="post" class="mt-6 max-w-xl space-y-4">
                @csrf
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                @if($errors->any())<ul class="rounded bg-red-50 px-3 py-2 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label" for="name">Name</label><input id="name" name="name" value="{{ old('name') }}" required class="input"></div>
                    <div><label class="label" for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required class="input"></div>
                </div>
                <div><label class="label" for="subject">Subject</label><input id="subject" name="subject" value="{{ old('subject') }}" class="input"></div>
                <div><label class="label" for="message">Message</label><textarea id="message" name="message" rows="6" required minlength="10" class="input">{{ old('message') }}</textarea></div>
                <button class="btn-primary" type="submit">Send message</button>
            </form>
        </div>
        @include('partials.sidebar')
    </div>
</div>
@endsection
