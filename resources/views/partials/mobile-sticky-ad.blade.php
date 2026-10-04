@php $sticky = setting('mobile_sticky_ad', 1) ? \App\Models\Ad::forSlot('mobile_sticky') : collect(); @endphp
@if($sticky->isNotEmpty())
    <div id="sticky-ad" class="fixed inset-x-0 bottom-0 z-40 border-t border-ink-300 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-lg backdrop-blur md:hidden">
        <button type="button" class="absolute -top-6 right-2 rounded-t bg-ink-900 px-2 py-0.5 text-xs text-white" aria-label="Close ad" onclick="document.getElementById('sticky-ad').remove()">✕</button>
        <div class="mx-auto flex max-h-24 items-center justify-center overflow-hidden px-2 py-1">
            @foreach($sticky as $ad)
                <div class="text-center">@if($ad->code){!! $ad->code !!}@elseif($ad->image)<a href="{{ $ad->url ?: '#' }}" rel="nofollow sponsored noopener" target="_blank"><img src="{{ media_url($ad->image) }}" alt="{{ $ad->name }}" class="mx-auto max-h-20"></a>@endif</div>
            @endforeach
        </div>
    </div>
@endif
