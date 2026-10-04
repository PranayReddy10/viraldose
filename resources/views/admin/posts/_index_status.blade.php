@if($post->index_status === 'PASS')<span class="badge-green" title="{{ $post->index_coverage }} · checked {{ $post->index_checked_at?->diffForHumans() }}">Indexed</span>
@elseif($post->index_status === 'NEUTRAL')<span class="badge-yellow" title="{{ $post->index_coverage }}">Not indexed</span>
@elseif($post->index_status === 'FAIL')<span class="badge-red" title="{{ $post->index_coverage }}">Error</span>
@elseif($post->indexing_requested_at)<span class="badge-blue" title="Submitted {{ $post->indexing_requested_at->diffForHumans() }}">Submitted</span>
@else<span class="badge-gray">—</span>@endif
