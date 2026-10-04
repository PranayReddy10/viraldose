@php $crumbs = app(\App\Services\Seo::class)->breadcrumbs; @endphp
@if(count($crumbs) > 1)
    <nav aria-label="Breadcrumb" class="text-xs text-ink-500 overflow-x-auto no-scrollbar">
        <ol class="flex items-center gap-1 whitespace-nowrap">
            @foreach($crumbs as $i => $crumb)
                <li class="flex items-center gap-1">
                    @if($loop->last)
                        <span aria-current="page" class="truncate max-w-[60vw]">{{ $crumb['name'] }}</span>
                    @else
                        <a href="{{ $crumb['url'] }}" class="hover:text-brand-600">{{ $crumb['name'] }}</a>
                        <span aria-hidden="true">/</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
