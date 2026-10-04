@php $ads = \App\Models\Ad::forSlot($slot); @endphp
@foreach($ads as $ad)
    <div class="ad-slot ad-{{ $slot }} my-4 text-center {{ $ad->deviceClass() }}" data-ad-slot="{{ $slot }}">
        @if($ad->code)
            {!! $ad->code !!}
        @elseif($ad->image)
            <a href="{{ $ad->url ?: '#' }}" rel="nofollow sponsored noopener" target="_blank"><img src="{{ media_url($ad->image) }}" alt="{{ $ad->name }}" loading="lazy" class="mx-auto"></a>
        @endif
    </div>
@endforeach
