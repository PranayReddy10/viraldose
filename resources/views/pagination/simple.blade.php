@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex justify-between">
        @if ($paginator->onFirstPage())<span></span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-outline">← Previous</a>@endif
        @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-outline">Next →</a>@endif
    </nav>
@endif
