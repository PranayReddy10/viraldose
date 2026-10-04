@props(['post', 'size' => 'medium', 'sizes' => '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw', 'eager' => false, 'class' => ''])
@php
    $src = $post->imageUrl($size);
    $srcset = $post->imageSrcset();
    [$w, $h] = match ($size) { 'small' => [400, 225], 'large' => [1200, 675], default => [800, 450] };
@endphp
@if($src)
    <img src="{{ $src }}" @if($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
         alt="{{ $post->imageAltText() }}" width="{{ $w }}" height="{{ $h }}"
         loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" @if($eager) fetchpriority="high" @endif
         class="h-full w-full object-cover {{ $class }}">
@else
    <div class="flex h-full w-full items-center justify-center bg-ink-100 text-ink-300 {{ $class }}" aria-hidden="true">
        <span class="text-3xl font-black">{{ \Illuminate\Support\Str::of(site_name())->substr(0, 2)->upper() }}</span>
    </div>
@endif
