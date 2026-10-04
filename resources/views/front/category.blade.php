@extends('layouts.app')
@section('content')
<x-archive :posts="$posts" :title="$category->name" :subtitle="$category->description">
    <x-slot:before>
        @if($category->children->isNotEmpty())
            <ul class="mb-6 flex flex-wrap gap-2">
                @foreach($category->children as $child)
                    <li><a href="{{ $child->url() }}" class="rounded-full border border-ink-300 px-3 py-1 text-xs font-semibold hover:border-brand-600 hover:text-brand-600">{{ $child->name }}</a></li>
                @endforeach
            </ul>
        @endif
        @include('partials.ad', ['slot' => 'category_top'])
    </x-slot:before>
</x-archive>
@endsection
