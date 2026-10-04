@extends('layouts.app')
@section('progress', '1')

@section('content')
@php
    $shareUrl = urlencode($post->url());
    $shareText = urlencode($post->title);
    $inContentAds = \App\Models\Ad::forSlot('post_in_content');
    $embeds = app(\App\Services\EmbedRenderer::class);
    $content = $embeds->render($post->content);
    $video = $post->video();
    if ($inContentAds->isNotEmpty()) {
        $adHtml = view('partials.ad', ['slot' => 'post_in_content'])->render();
        $parts = preg_split('/(<\/p>)/i', $content, 4, PREG_SPLIT_DELIM_CAPTURE);
        if (count($parts) >= 7) {
            $content = implode('', array_slice($parts, 0, 6)).$adHtml.implode('', array_slice($parts, 6));
        }
    }
@endphp
<div class="container-site py-6">
    <div class="grid gap-10 lg:grid-cols-3">
        <article class="lg:col-span-2 min-w-0">
            <x-breadcrumbs />
            <header class="mt-4">
                @if($post->category)
                    <a href="{{ $post->category->url() }}" class="cat-badge" style="background: {{ $post->category->color }}">{{ $post->category->name }}</a>
                @endif
                <h1 class="mt-3 text-3xl font-black leading-tight tracking-tight sm:text-4xl">{{ $post->title }}</h1>
                @if($post->excerpt)<p class="mt-3 text-lg text-ink-700">{{ $post->excerpt }}</p>@endif
                <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-ink-500">
                    @if($post->author)
                        <div class="flex items-center gap-2">
                            @if($post->author->avatarUrl())
                                <img src="{{ $post->author->avatarUrl() }}" alt="" width="32" height="32" class="h-8 w-8 rounded-full object-cover">
                            @endif
                            <span>By <a href="{{ $post->author->url() }}" rel="author" class="font-semibold text-ink-900 hover:text-brand-600">{{ $post->author->name }}</a></span>
                        </div>
                    @endif
                    <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->timezone(setting('timezone_display', 'Asia/Kolkata'))->format('M d, Y, h:i A') }} IST</time>
                    @if($post->updated_at && $post->published_at && $post->updated_at->gt($post->published_at->addHours(2)))
                        <span>Updated <time datetime="{{ $post->updated_at->toIso8601String() }}">{{ $post->updated_at->diffForHumans() }}</time></span>
                    @endif
                    <span>{{ $post->reading_time }} min read</span>
                </div>
                @unless($post->isPublished())
                    <p class="mt-3 rounded bg-yellow-50 px-3 py-2 text-sm text-yellow-800">Preview — this post is not published yet.</p>
                @endunless
            </header>

            {{-- Share --}}
            <div class="mt-5 flex flex-wrap gap-2 text-sm">
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}" target="_blank" rel="noopener nofollow" class="btn bg-[#1877f2] text-white">Facebook</a>
                <a href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareText }}" target="_blank" rel="noopener nofollow" class="btn bg-black text-white">X</a>
                <a href="https://api.whatsapp.com/send?text={{ $shareText }}%20{{ $shareUrl }}" target="_blank" rel="noopener nofollow" class="btn bg-[#25d366] text-white">WhatsApp</a>
                <a href="https://t.me/share/url?url={{ $shareUrl }}&text={{ $shareText }}" target="_blank" rel="noopener nofollow" class="btn bg-[#229ed9] text-white">Telegram</a>
                <button type="button" data-share class="btn-outline">Share / Copy link</button>
            </div>

            @if($video)
                <figure class="mt-6 embed embed-video">
                    @if($video['type'] === 'file')
                        <video controls preload="metadata" playsinline class="w-full rounded-lg bg-black aspect-video" @if($post->imageUrl('large')) poster="{{ $post->imageUrl('large') }}" @endif>
                            <source src="{{ $video['src'] }}">Your browser does not support video playback.
                        </video>
                    @else
                        <iframe src="{{ $video['src'] }}" title="{{ $post->title }}" loading="eager" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" class="w-full rounded-lg aspect-video bg-black"></iframe>
                    @endif
                    @if($post->image_caption)<figcaption class="mt-2 text-center text-xs text-ink-500">{{ $post->image_caption }}</figcaption>@endif
                </figure>
            @elseif($post->post_type === 'audio' && $post->audio_url)
                <div class="mt-6 rounded-lg border border-ink-100 p-4 sm:flex sm:items-center sm:gap-4">
                    @if($post->image)<div class="mb-3 w-full overflow-hidden rounded aspect-video sm:mb-0 sm:w-40 sm:aspect-square"><x-post-image :post="$post" size="small" sizes="160px" :eager="true" /></div>@endif
                    <div class="min-w-0 flex-1"><p class="mb-2 text-xs font-bold uppercase text-ink-500">Listen</p><audio controls preload="metadata" class="w-full" src="{{ $post->audio_url }}"></audio></div>
                </div>
            @elseif($post->post_type === 'gallery' && $post->images->isNotEmpty())
                <section class="mt-6" aria-label="Photo gallery">
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($post->images as $image)
                            <figure class="{{ $loop->first ? 'sm:col-span-2' : '' }}">
                                <a href="{{ $image->url('large') }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg bg-ink-100 {{ $loop->first ? 'aspect-video' : 'aspect-[4/3]' }}">
                                    <img src="{{ $image->url($loop->first ? 'large' : 'medium') }}" alt="{{ $image->caption ?: $post->title.' – photo '.$loop->iteration }}" width="{{ $loop->first ? 1200 : 800 }}" height="{{ $loop->first ? 675 : 600 }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async" class="h-full w-full object-cover">
                                </a>
                                <figcaption class="mt-1 text-xs text-ink-500">{{ $loop->iteration }}/{{ $post->images->count() }}@if($image->caption) · {{ $image->caption }}@endif</figcaption>
                            </figure>
                        @endforeach
                    </div>
                </section>
            @elseif($post->image)
                <figure class="mt-6">
                    <div class="overflow-hidden rounded-lg aspect-video bg-ink-100">
                        <x-post-image :post="$post" size="large" sizes="(min-width: 1024px) 66vw, 100vw" :eager="true" />
                    </div>
                    @if($post->image_caption)<figcaption class="mt-2 text-center text-xs text-ink-500">{{ $post->image_caption }}</figcaption>@endif
                </figure>
            @endif

            @include('partials.ad', ['slot' => 'post_before_content'])

            <div class="article-body mt-8">
                {!! $content !!}
            </div>

            @if($post->images->isNotEmpty() && $post->post_type !== 'gallery')
                <section class="mt-8" aria-label="Photo gallery">
                    <h2 class="section-title">Gallery</h2>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach($post->images as $image)
                            <figure>
                                <a href="{{ $image->url('large') }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg aspect-[4/3] bg-ink-100">
                                    <img src="{{ $image->url('medium') }}" alt="{{ $image->caption ?: $post->title.' – photo '.$loop->iteration }}" width="800" height="600" loading="lazy" decoding="async" class="h-full w-full object-cover">
                                </a>
                                @if($image->caption)<figcaption class="mt-1 text-xs text-ink-500">{{ $image->caption }}</figcaption>@endif
                            </figure>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($post->files->isNotEmpty())
                <section class="mt-8 rounded-lg border border-ink-100 p-5" aria-label="Downloads">
                    <h2 class="font-bold">Downloads</h2>
                    <ul class="mt-2 space-y-2 text-sm">
                        @foreach($post->files as $file)
                            <li class="flex items-center justify-between gap-3">
                                <a href="{{ route('post.file.download', $file) }}" rel="nofollow" class="font-medium text-brand-600 hover:underline">{{ $file->name }}</a>
                                <span class="text-xs text-ink-500">{{ $file->humanSize() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if($post->source_name || $post->source_url)
                <p class="mt-6 text-sm text-ink-500">Source:
                    @if($post->source_url)<a href="{{ $post->source_url }}" rel="nofollow noopener" target="_blank" class="underline">{{ $post->source_name ?: parse_url($post->source_url, PHP_URL_HOST) }}</a>@else{{ $post->source_name }}@endif
                </p>
            @endif

            @if($post->tags->isNotEmpty())
                <ul class="mt-8 flex flex-wrap gap-2" aria-label="Tags">
                    @foreach($post->tags as $tag)
                        <li><a href="{{ $tag->url() }}" class="inline-block rounded bg-ink-100 px-3 py-1 text-xs font-semibold text-ink-700 hover:bg-brand-600 hover:text-white">#{{ $tag->name }}</a></li>
                    @endforeach
                </ul>
            @endif

            @include('partials.ad', ['slot' => 'post_after_content'])

            @if($post->author && $post->author->bio)
                <section class="mt-10 flex gap-4 rounded-lg border border-ink-100 p-5">
                    @if($post->author->avatarUrl())<img src="{{ $post->author->avatarUrl() }}" alt="{{ $post->author->name }}" width="64" height="64" class="h-16 w-16 rounded-full object-cover">@endif
                    <div>
                        <h2 class="font-bold"><a href="{{ $post->author->url() }}" class="hover:text-brand-600">{{ $post->author->name }}</a></h2>
                        <p class="mt-1 text-sm text-ink-700">{{ $post->author->bio }}</p>
                    </div>
                </section>
            @endif

            {{-- Prev / Next --}}
            @if($prev || $next)
                <nav class="mt-10 grid gap-4 border-t border-ink-100 pt-6 sm:grid-cols-2" aria-label="More articles">
                    @if($prev)
                        <a href="{{ $prev->url() }}" class="group">
                            <span class="text-xs uppercase text-ink-500">← Previous</span>
                            <span class="block font-semibold group-hover:text-brand-600 line-clamp-2">{{ $prev->title }}</span>
                        </a>
                    @endif
                    @if($next)
                        <a href="{{ $next->url() }}" class="group sm:text-right">
                            <span class="text-xs uppercase text-ink-500">Next →</span>
                            <span class="block font-semibold group-hover:text-brand-600 line-clamp-2">{{ $next->title }}</span>
                        </a>
                    @endif
                </nav>
            @endif

            @if($related->isNotEmpty())
                <section class="mt-12">
                    <h2 class="section-title">Related Stories</h2>
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($related as $item)
                            <x-post-card :post="$item" heading="h3" />
                        @endforeach
                    </div>
                </section>
            @endif

            @if(setting('comments_enabled') && $post->allow_comments)
                <section id="comments" class="mt-12">
                    <h2 class="section-title">Comments ({{ $comments->count() }})</h2>
                    @if(session('status'))<p class="mb-4 rounded bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>@endif
                    <div class="space-y-6">
                        @foreach($comments as $comment)
                            <div class="flex gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-ink-100 font-bold text-ink-700">{{ strtoupper(substr($comment->name, 0, 1)) }}</div>
                                <div class="flex-1">
                                    <p class="text-sm"><span class="font-semibold">{{ $comment->name }}</span> <span class="text-ink-500">· {{ $comment->created_at->diffForHumans() }}</span></p>
                                    <p class="mt-1 text-sm text-ink-700 whitespace-pre-line">{{ $comment->body }}</p>
                                    <button type="button" class="mt-1 text-xs font-semibold text-brand-600" data-reply-to="{{ $comment->id }}" data-reply-name="{{ $comment->name }}">Reply</button>
                                    @foreach($comment->replies as $reply)
                                        <div class="mt-4 flex gap-3 border-l-2 border-ink-100 pl-3">
                                            <div class="flex-1">
                                                <p class="text-sm"><span class="font-semibold">{{ $reply->name }}</span> <span class="text-ink-500">· {{ $reply->created_at->diffForHumans() }}</span></p>
                                                <p class="mt-1 text-sm text-ink-700 whitespace-pre-line">{{ $reply->body }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <form id="comment-form" action="{{ route('comments.store', $post) }}" method="post" class="mt-8 space-y-3 rounded-lg border border-ink-100 p-5">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ old('parent_id') }}">
                        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                        <h3 class="font-bold">Leave a comment</h3>
                        <p id="reply-note" class="hidden text-xs text-ink-500"></p>
                        <button type="button" id="cancel-reply" class="text-xs text-ink-500 underline">Cancel reply</button>
                        @if($errors->any())<ul class="text-sm text-red-600">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div><label class="label" for="c-name">Name</label><input id="c-name" name="name" value="{{ old('name') }}" required maxlength="100" class="input"></div>
                            <div><label class="label" for="c-email">Email (not published)</label><input id="c-email" type="email" name="email" value="{{ old('email') }}" required class="input"></div>
                        </div>
                        <div><label class="label" for="c-body">Comment</label><textarea id="c-body" name="body" rows="4" required minlength="3" maxlength="2000" class="input">{{ old('body') }}</textarea></div>
                        <button class="btn-primary" type="submit">Post comment</button>
                    </form>
                </section>
            @endif
        </article>

        @include('partials.sidebar')
    </div>
</div>
@endsection
@push('scripts'){!! $embeds->scripts() !!}@endpush
