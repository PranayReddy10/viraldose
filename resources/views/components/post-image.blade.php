@props(['post', 'size' => 'medium', 'sizes' => '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw', 'eager' => false, 'class' => ''])
@php
    $src = $post->imageUrl($size);
    $srcset = $post->imageSrcset();
    [$w, $h] = match ($size) { 'small' => [400, 225], 'large' => [1200, 675], default => [800, 450] };
@endphp
@php
    if (! $src && ($post->post_type ?? null) === 'video' && ($v = \App\Services\EmbedRenderer::video($post->video_url ?? null)) && $v['poster']) {
        $src = $v['poster'];
        $srcset = null;
    }
@endphp
@if($src)
    <img src="{{ $src }}" @if($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
         alt="{{ $post->imageAltText() }}" width="{{ $w }}" height="{{ $h }}"
         loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" @if($eager) fetchpriority="high" @endif
         class="h-full w-full object-cover {{ $class }}">
    @if(($post->post_type ?? null) === 'video')
        <span class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true"><span class="flex h-12 w-12 items-center justify-center rounded-full bg-black/60 text-white ring-2 ring-white/80"><svg class="ml-1 h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span></span>
    @elseif(($post->post_type ?? null) === 'gallery')
        <span class="pointer-events-none absolute bottom-2 right-2 rounded bg-black/60 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white" aria-hidden="true">Gallery</span>
    @endif
@else
    <div class="flex h-full w-full items-center justify-center bg-ink-100 text-ink-300 {{ $class }}" aria-hidden="true">
        <span class="text-3xl font-black">{{ \Illuminate\Support\Str::of(site_name())->substr(0, 2)->upper() }}</span>
    </div>
@endif
