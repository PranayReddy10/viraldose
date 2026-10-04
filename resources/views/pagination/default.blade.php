@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-between gap-4">
        @if ($paginator->onFirstPage())
            <span class="btn-outline opacity-50 cursor-not-allowed">← Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-outline">← Previous</a>
        @endif

        <ul class="hidden sm:flex items-center gap-1">
            @foreach ($elements as $element)
                @if (is_string($element))<li class="px-2 text-ink-500">{{ $element }}</li>@endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-brand-600 text-sm font-semibold text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md text-sm font-semibold hover:bg-ink-100">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
        </ul>
        <span class="sm:hidden text-sm text-ink-500">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-outline">Next →</a>
        @else
            <span class="btn-outline opacity-50 cursor-not-allowed">Next →</span>
        @endif
    </nav>
@endif
