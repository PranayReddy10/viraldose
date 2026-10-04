{{-- Generic archive layout used by category / tag / author / search pages --}}
@props(['posts', 'title', 'subtitle' => null, 'heading' => null])
<div class="container-site py-6">
    <x-breadcrumbs />
    <header class="mt-3 mb-6 border-b border-ink-100 pb-4">
        {{ $heading ?? '' }}
        @if(!isset($heading))
            <h1 class="text-3xl font-black tracking-tight">{{ $title }}</h1>
            @if($subtitle)<p class="mt-2 max-w-3xl text-ink-700">{{ $subtitle }}</p>@endif
        @endif
    </header>
    <div class="grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2">
            {{ $before ?? '' }}
            @if($posts && $posts->count())
                <div class="grid gap-8 sm:grid-cols-2">
                    @foreach($posts as $post)
                        <x-post-card :post="$post" heading="h2" :eager="$loop->first" />
                    @endforeach
                </div>
                <div class="mt-10">{{ $posts->links() }}</div>
            @else
                <p class="text-ink-500">No articles found.</p>
            @endif
        </div>
        @include('partials.sidebar')
    </div>
</div>
