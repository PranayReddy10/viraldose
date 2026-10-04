@if($post->trashed())<span class="badge-red">Trashed</span>
@elseif($post->isScheduled())<span class="badge-blue">Scheduled</span>
@elseif($post->status === 'published')<span class="badge-green">Published</span>
@elseif($post->status === 'archived')<span class="badge-gray">Archived</span>
@else<span class="badge-yellow">Draft</span>@endif
