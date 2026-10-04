@extends('layouts.admin')
@section('title', $message->subject ?: 'Message')
@section('content')
<div class="card max-w-2xl p-5">
    <p class="text-sm text-ink-500">From <strong>{{ $message->name }}</strong> &lt;<a href="mailto:{{ $message->email }}" class="underline">{{ $message->email }}</a>&gt; · {{ $message->created_at->format('d M Y H:i') }} · IP {{ $message->ip_address }}</p>
    <div class="mt-4 whitespace-pre-line">{{ $message->message }}</div>
    <div class="mt-6 flex gap-3"><a href="mailto:{{ $message->email }}?subject=Re: {{ $message->subject }}" class="btn-primary">Reply by email</a><x-admin.delete-button :action="route('admin.messages.destroy', $message)" class="btn-outline !text-red-600" /></div>
</div>
@endsection
