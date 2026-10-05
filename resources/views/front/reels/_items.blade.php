@php
    $adEvery = (int) setting('reels_ad_every', 4);
    $feedAds = \App\Models\Ad::forSlot('reels_feed');
    $hasMore = $reels instanceof \Illuminate\Pagination\LengthAwarePaginator && $reels->hasMorePages();
@endphp
@foreach($reels as $i => $reel)
    @php $n = $offset + $loop->iteration; @endphp
    <section class="reel-slide relative h-[100dvh] w-full snap-start snap-always bg-black" data-view-url="{{ $reel->url() }}" aria-label="{{ $reel->title }}">
        <div class="mx-auto flex h-full max-w-[480px] items-center justify-center">
            @if($reel->isPlayable())
                <video class="h-full w-full object-contain" playsinline muted loop preload="metadata" @if($reel->thumbnailUrl()) poster="{{ $reel->thumbnailUrl() }}" @endif>
                    <source src="{{ $reel->videoUrl() }}">
                </video>
            @elseif($reel->isImage() && $reel->imageUrl())
                <div class="absolute inset-0 overflow-hidden"><img src="{{ $reel->thumbnailUrl() }}" alt="" aria-hidden="true" class="h-full w-full scale-110 object-cover opacity-50 blur-2xl"></div>
                <img src="{{ $reel->imageUrl() }}" alt="{{ $reel->title }}" loading="{{ $loop->first && $offset === 0 ? 'eager' : 'lazy' }}" decoding="async" class="reel-photo relative h-full w-full object-contain">
            @elseif($reel->source_type === 'youtube')
                <iframe data-src="{{ $reel->embedUrl() }}&autoplay=1&mute=1" title="{{ $reel->title }}" class="aspect-[9/16] max-h-full w-full" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
            @elseif($reel->source_type === 'instagram')
                <iframe data-src="{{ $reel->embedUrl() }}" title="{{ $reel->title }}" class="aspect-[9/16] max-h-full w-full bg-white" allow="encrypted-media" scrolling="no"></iframe>
            @endif
        </div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent px-4 pb-10 pt-24 sm:pb-6">
            <div class="mx-auto flex max-w-[480px] items-end gap-3">
                <div class="min-w-0 flex-1">
                    @if($reel->category)<a href="{{ $reel->category->url() }}" class="pointer-events-auto cat-badge" style="background: {{ $reel->category->color }}">{{ $reel->category->name }}</a>@endif
                    <h2 class="mt-1 text-base font-bold leading-snug sm:text-lg">{{ $reel->title }}</h2>
                    @if($reel->caption)<p class="mt-1 text-sm text-gray-200 line-clamp-2">{{ $reel->caption }}</p>@endif
                    @if($reel->post)<a href="{{ $reel->post->url() }}" class="pointer-events-auto mt-2 inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-bold text-ink-900">Read the full story →</a>@endif
                </div>
                <div class="pointer-events-auto flex flex-col items-center gap-4 text-xs">
                    <button type="button" data-share-url="{{ $reel->url() }}" data-share-title="{{ $reel->title }}" class="flex flex-col items-center gap-1" aria-label="Share">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-white/15"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12v7a1 1 0 001 1h14a1 1 0 001-1v-7M16 6l-4-4-4 4M12 2v13"/></svg></span>Share
                    </button>
                    @if($reel->source_type === 'youtube' || $reel->source_type === 'instagram')
                        <a href="{{ $reel->external_url }}" target="_blank" rel="noopener nofollow" class="flex flex-col items-center gap-1"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-white/15 text-sm font-black">{{ $reel->source_type === 'youtube' ? '▶' : '◎' }}</span>{{ $reel->source_type === 'youtube' ? 'YouTube' : 'Instagram' }}</a>
                    @endif
                    <span class="flex flex-col items-center gap-1 text-gray-300"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-white/15"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12zM12 15a3 3 0 100-6 3 3 0 000 6z"/></svg></span>{{ $reel->views >= 1000 ? round($reel->views / 1000, 1).'k' : $reel->views }}</span>
                </div>
            </div>
        </div>
    </section>
    @if($adEvery > 0 && $feedAds->isNotEmpty() && $n % $adEvery === 0)
        <section class="reel-slide relative flex h-[100dvh] w-full snap-start snap-always items-center justify-center bg-ink-900 px-4" aria-label="Advertisement">
            <div class="w-full max-w-[480px] text-center">
                <p class="mb-3 text-[10px] uppercase tracking-widest text-gray-400">Advertisement</p>
                @include('partials.ad', ['slot' => 'reels_feed'])
            </div>
        </section>
    @endif
@endforeach
@if($hasMore)<span data-next-page="{{ $reels->nextPageUrl() }}&fragment=1" class="hidden"></span>@endif
