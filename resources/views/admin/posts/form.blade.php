@extends('layouts.admin')
@section('title', $post->exists ? 'Edit Article' : 'Add Article')
@section('actions')
    @if($post->exists)<a href="{{ $post->url() }}{{ $post->isPublished() ? '' : '?preview=1' }}" target="_blank" class="btn-outline !px-3 !py-1.5 text-xs"><x-admin.icon name="external" class="h-4 w-4" /> {{ $post->isPublished() ? 'View' : 'Preview' }}</a>@endif
    <a href="{{ route('admin.posts.index') }}" class="btn bg-teal-600 text-white hover:bg-teal-700 !px-3 !py-1.5 text-xs"><x-admin.icon name="posts" class="h-4 w-4" /> Posts</a>
@endsection
@section('content')
@php
    $catOptions = [];
    foreach ($categories as $c) { $catOptions[$c->id] = $c->name; foreach ($c->children as $ch) { $catOptions[$ch->id] = '— '.$ch->name; } }
    $user = auth()->user();
    $scheduled = old('scheduled', $post->isScheduled());
@endphp
<form method="post" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-3" id="post-form" data-url-format="{{ \App\Support\PostUrl::format() }}">
    @csrf @if($post->exists) @method('PUT') @endif
    <input type="hidden" name="save_as" id="save_as" value="">

    <div class="xl:col-span-2 space-y-6">
        {{-- Post details --}}
        <section class="card p-5">
            <h2 class="mb-4 text-lg font-bold">Post Details</h2>
            <div class="grid gap-4 sm:grid-cols-[1fr_180px]">
                <x-admin.field label="Title" name="title" :value="$post->title" required :max="200" counter slug-source />
                <x-admin.select label="Post Type" name="post_type" :value="$post->post_type ?: 'article'" :options="\App\Models\Post::TYPES" />
            </div>
            <div data-post-type="video" class="hidden">
                <x-admin.field label="Video URL" name="video_url" type="url" :value="$post->video_url" help="YouTube, Vimeo or a direct .mp4 link. Shown as the main media above the article; the featured image becomes the poster/thumbnail." />
            </div>
            <div data-post-type="audio" class="hidden">
                <x-admin.field label="Audio URL" name="audio_url" type="url" :value="$post->audio_url" help="Direct .mp3/.m4a link. An audio player is shown above the article." />
            </div>
            <div data-post-type="gallery" class="hidden mb-4 rounded bg-ink-100 px-3 py-2 text-xs text-ink-700">Gallery posts show the <strong>Additional Images</strong> as a large photo grid above the article text. Add captions for each photo.</div>
            <x-admin.field label="Slug" name="slug" :value="$post->slug" help="If you leave it blank, it will be generated automatically. Changing a published slug breaks old links — add a redirect." slug-target />
            <x-admin.field label="Summary & Description (Meta Tag)" name="excerpt" type="textarea" :value="$post->excerpt" :max="500" counter help="Shown in listings and used as the meta description unless you set one below." />
            <x-admin.field label="Keywords (Meta Tag)" name="meta_keywords" :value="$post->meta_keywords" help="Comma separated. The first keyword is the focus keyword for the SEO check." />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field label="Tags" name="tags" :value="$tagString" help="Comma separated, e.g. cricket, ipl 2026" />
                <x-admin.field label="Optional URL (source)" name="source_url" type="url" :value="$post->source_url" help="Original source link, shown under the article." />
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-0 flex-1"><x-admin.field label="Source name" name="source_name" :value="$post->source_name" /></div>
                @if($post->exists && $post->source_url)<button type="submit" form="form-pull-content" class="btn-outline mb-4 !px-3 !py-1.5 text-xs" title="Download the full article text and images from the source URL and replace the content"><x-admin.icon name="download" class="h-4 w-4" /> Pull full article from source</button>@endif
            </div>
            <div class="mt-2 grid gap-x-8 gap-y-1 sm:grid-cols-2">
                @if($user->canManageAllPosts())
                    <x-admin.checkbox label="Add to Slider" name="is_slider" :checked="$post->is_slider" />
                    <x-admin.checkbox label="Add to Featured" name="is_featured" :checked="$post->is_featured" />
                    <x-admin.checkbox label="Add to Breaking" name="is_breaking" :checked="$post->is_breaking" />
                    <x-admin.checkbox label="Add to Recommended" name="is_recommended" :checked="$post->is_recommended" />
                @endif
                <x-admin.checkbox label="Allow comments" name="allow_comments" :checked="$post->allow_comments" />
                <x-admin.checkbox label="Hide from search engines (noindex)" name="noindex" :checked="$post->noindex" />
            </div>
        </section>

        {{-- Content --}}
        <section class="card p-5">
            <h2 class="mb-4 text-lg font-bold">Content</h2>
            <div id="editor-toolbar">
                <span class="ql-formats"><select class="ql-header"><option value="2">Heading 2</option><option value="3">Heading 3</option><option value="4">Heading 4</option><option selected>Normal</option></select></span>
                <span class="ql-formats"><button class="ql-bold" title="Bold"></button><button class="ql-italic" title="Italic"></button><button class="ql-underline" title="Underline"></button><button class="ql-strike" title="Strike"></button></span>
                <span class="ql-formats"><button class="ql-list" value="ordered" title="Numbered list"></button><button class="ql-list" value="bullet" title="Bullet list"></button></span>
                <span class="ql-formats"><button class="ql-blockquote" title="Quote"></button><button class="ql-code-block" title="Code"></button><button class="ql-link" title="Link"></button><button class="ql-image" title="Upload image"></button><select class="ql-align"></select></span>
                <span class="ql-formats ql-social">
                    <button type="button" class="ql-youtube" title="Embed YouTube video"><svg viewBox="0 0 24 24" class="ql-stroke-none"><path fill="#ff0000" d="M23 7.2a3 3 0 00-2.1-2.1C19 4.6 12 4.6 12 4.6s-7 0-8.9.5A3 3 0 001 7.2 31 31 0 00.5 12 31 31 0 001 16.8a3 3 0 002.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 002.1-2.1c.4-1.6.5-3.2.5-4.8s-.1-3.2-.5-4.8z"/><path fill="#fff" d="M9.7 15.1V8.9l6 3.1z"/></svg></button>
                    <button type="button" class="ql-twitter" title="Embed X / Twitter post"><svg viewBox="0 0 24 24"><path fill="#000" d="M18.2 2h3.4l-7.4 8.5L23 22h-6.8l-5.3-7-6.1 7H1.4l7.9-9.1L1 2h7l4.8 6.4zm-1.2 18h1.9L7.1 3.9H5.1z"/></svg></button>
                    <button type="button" class="ql-instagram" title="Embed Instagram post"><svg viewBox="0 0 24 24"><defs><linearGradient id="ig" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#fdc468"/><stop offset=".5" stop-color="#df4996"/><stop offset="1" stop-color="#4f5bd5"/></linearGradient></defs><rect x="2" y="2" width="20" height="20" rx="5" fill="url(#ig)"/><circle cx="12" cy="12" r="4.5" fill="none" stroke="#fff" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.3" fill="#fff"/></svg></button>
                    <button type="button" class="ql-facebook" title="Embed Facebook post"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#1877f2"/><path fill="#fff" d="M13.3 20v-7h2.3l.4-2.8h-2.7V8.5c0-.8.2-1.3 1.4-1.3H16V4.7c-.2 0-1.1-.1-2.1-.1-2.1 0-3.5 1.3-3.5 3.6v2H8v2.8h2.4v7z"/></svg></button>
                </span>
                <span class="ql-formats"><button class="ql-clean" title="Clear formatting"></button></span>
            </div>
            <div id="editor" data-upload-url="{{ route('admin.media.upload') }}" class="bg-white"></div>
            <textarea id="content" name="content" class="hidden">{{ old('content', $post->content) }}</textarea>
            @error('content')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            <p class="mt-2 text-xs text-ink-500">Image button uploads inline photos. The coloured buttons embed a <strong>YouTube video</strong>, <strong>X post</strong>, <strong>Instagram post</strong> or <strong>Facebook post</strong> at the cursor — paste the public URL. Links to other ViralDose stories help Google crawl the site.</p>
        </section>

        {{-- SEO --}}
        <section class="card p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-bold">SEO</h2>
                @if($seo)
                    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-bold {{ ['good' => 'bg-green-100 text-green-800', 'ok' => 'bg-yellow-100 text-yellow-800', 'poor' => 'bg-red-100 text-red-800'][$seo['grade']] }}">SEO score {{ $seo['score'] }}/100</span>
                @endif
            </div>
            <div class="mb-5 rounded border border-ink-100 bg-ink-100/40 p-4">
                <p class="mb-1 text-xs text-ink-500">Google preview</p>
                <p id="pv-title" class="truncate text-lg leading-tight text-[#1a0dab]">Title</p>
                <p id="pv-url" class="truncate text-sm text-[#006621]"></p>
                <p id="pv-desc" class="text-sm text-ink-700 line-clamp-2"></p>
            </div>
            <div class="grid gap-x-6 sm:grid-cols-2">
                <x-admin.field label="Meta title" name="meta_title" :value="$post->meta_title" :max="60" counter help="Blank = post title. Aim for 50–60 characters." />
                <x-admin.field label="Canonical URL" name="canonical_url" type="url" :value="$post->canonical_url" help="Only if first published elsewhere." />
            </div>
            <x-admin.field label="Meta description" name="meta_description" type="textarea" :rows="2" :value="$post->meta_description" :max="160" counter help="Aim for 120–160 characters. Falls back to the summary." />
            @if($seo)
                <h3 class="mb-2 mt-4 text-sm font-bold">On-page checks</h3>
                <ul class="grid gap-1 text-sm sm:grid-cols-2">
                    @foreach($seo['checks'] as $check)
                        <li class="flex items-start gap-2 rounded px-2 py-1 {{ ['pass' => 'text-green-800', 'warn' => 'text-yellow-800', 'fail' => 'text-red-800'][$check['status']] }}">
                            <x-admin.icon :name="['pass' => 'check', 'warn' => 'warn', 'fail' => 'x'][$check['status']]" class="mt-0.5 h-4 w-4 shrink-0" />
                            <span><strong>{{ $check['label'] }}</strong> <span class="text-ink-700">— {{ $check['hint'] }}</span></span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-xs text-ink-500">Save the post to run the on-page SEO check.</p>
            @endif
        </section>
    </div>

    <div class="space-y-6">
        {{-- Publish --}}
        <section class="card p-5">
            <h2 class="mb-3 text-lg font-bold">Publish</h2>
            <x-admin.select label="Status" name="status" :value="$post->status" :options="['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']" />
            @if($user->canManageAllPosts())
                <x-admin.select label="Author" name="user_id" :value="$post->user_id ?? $user->id" :options="$authors->pluck('name', 'id')" />
            @endif
            <label class="mb-2 flex items-center gap-2 text-sm font-medium"><input type="hidden" name="scheduled" value="0"><input type="checkbox" name="scheduled" value="1" id="scheduled-toggle" class="rounded" @checked($scheduled)> Scheduled Post</label>
            <div id="scheduled-fields" class="{{ $scheduled ? '' : 'hidden' }}">
                <x-admin.field label="Publish date & time" name="published_at" type="datetime-local" :value="old('published_at', $post->published_at?->format('Y-m-d\TH:i'))" help="Goes live automatically at this time (server time zone {{ config('app.timezone') }})." />
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="submit" class="btn bg-yellow-500 text-white hover:bg-yellow-600" data-save-as="draft">Save as Draft</button>
                <button type="submit" class="btn-primary flex-1" data-save-as="publish">{{ $post->isPublished() ? 'Update' : 'Publish' }}</button>
            </div>
            @if($post->exists)<div class="mt-3 text-right"><button type="submit" form="form-trash" class="text-xs font-semibold text-red-600 hover:underline">Move to trash</button></div>@endif
        </section>

        {{-- Google index status --}}
        @if($post->exists)
        <section class="card p-5">
            <h2 class="mb-3 flex items-center gap-2 text-lg font-bold"><x-admin.icon name="google" class="h-5 w-5" /> Google</h2>
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-ink-500">Index status</dt><dd>@include('admin.posts._index_status')</dd></div>
                @if($post->index_coverage)<div class="flex justify-between gap-3"><dt class="text-ink-500">Coverage</dt><dd class="text-right">{{ $post->index_coverage }}</dd></div>@endif
                @if($post->last_crawled_at)<div class="flex justify-between"><dt class="text-ink-500">Last crawled</dt><dd>{{ $post->last_crawled_at->diffForHumans() }}</dd></div>@endif
                @if($post->indexing_requested_at)<div class="flex justify-between"><dt class="text-ink-500">Last submitted</dt><dd>{{ $post->indexing_requested_at->diffForHumans() }}</dd></div>@endif
            </dl>
            @if(session('inspection'))
                @php $r = session('inspection'); @endphp
                <div class="mt-3 rounded bg-ink-100 p-3 text-xs">
                    <p><strong>Verdict:</strong> {{ $r['verdict'] }} · {{ $r['coverage'] }}</p>
                    <p><strong>Robots:</strong> {{ $r['robots'] }} · <strong>Fetch:</strong> {{ $r['fetch'] }} · <strong>Mobile:</strong> {{ $r['mobile'] ?? 'n/a' }}</p>
                    @if($r['google_canonical'] && $r['google_canonical'] !== $r['user_canonical'])<p class="text-red-700"><strong>Google canonical differs:</strong> {{ $r['google_canonical'] }}</p>@endif
                    @if($r['link'])<a href="{{ $r['link'] }}" target="_blank" class="underline">Open in Search Console</a>@endif
                </div>
            @endif
            <div class="mt-3 flex flex-wrap gap-2">
                @if($googleReady)
                    <button type="submit" form="form-inspect" class="btn-outline !px-3 !py-1.5 text-xs"><x-admin.icon name="search" class="h-4 w-4" /> Inspect URL</button>
                @endif
                @if(($googleReady || $indexNowReady) && $post->isPublished())
                    <button type="submit" form="form-index-request" class="btn bg-blue-600 text-white hover:bg-blue-700 !px-3 !py-1.5 text-xs"><x-admin.icon name="send" class="h-4 w-4" /> Request indexing</button>
                @endif
                @if(! $googleReady && $user->isAdmin())
                    <a href="{{ route('admin.settings.edit', ['tab' => 'google']) }}" class="btn-outline !px-3 !py-1.5 text-xs">Connect Google to inspect &amp; index</a>
                @endif
            </div>
            <p class="mt-3 text-xs text-ink-500">Submitting tells Google (Indexing API) and Bing/Yandex (IndexNow) that this URL is new or changed. Inspection returns Google's live index status.</p>
            @if($indexingLogs->isNotEmpty())
                <ul class="mt-3 divide-y divide-ink-100 border-t border-ink-100 text-xs">
                    @foreach($indexingLogs as $log)
                        <li class="flex items-center gap-2 py-1.5"><span class="{{ $log->status === 'ok' ? 'text-green-600' : 'text-red-600' }}"><x-admin.icon :name="$log->status === 'ok' ? 'check' : 'x'" class="h-3.5 w-3.5" /></span><span class="font-medium">{{ str_replace('_', ' ', $log->provider) }}</span><span class="text-ink-500">{{ $log->action }}</span><span class="ml-auto text-ink-500">{{ $log->created_at->diffForHumans(null, true) }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>
        @endif

        {{-- WhatsApp Channel: no posting API exists, so copy / open WhatsApp with the text ready --}}
        @if($post->exists && $post->isPublished())
        <section class="card p-5">
            <h2 class="mb-1 flex items-center gap-2 text-lg font-bold"><svg class="h-5 w-5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#25d366"/><path d="M8.5 7.5c.3-.6.6-.6.9-.6h.6c.2 0 .4 0 .6.5l.8 1.9c.1.2 0 .4-.1.6l-.5.6c-.1.1-.2.3 0 .5.6 1 1.4 1.8 2.4 2.4.2.1.4.1.5 0l.6-.7c.2-.2.4-.2.6-.1l1.8.9c.2.1.4.2.4.4 0 .5-.2 1.3-.9 1.7-.6.4-1.6.6-3.6-.3-2.5-1.1-4.1-3.6-4.2-3.8-.1-.2-1-1.3-1-2.5 0-1.2.6-1.8.9-2z" fill="#fff"/></svg> WhatsApp Channel</h2>
            <p class="mb-3 text-xs text-ink-500"><strong>Copy &amp; open channel</strong> copies the text and opens your channel – long-press the message box, <strong>Paste</strong>, send. (WhatsApp does not allow websites to pre-fill channel messages.) The link shows the article image as a preview.</p>
            <textarea id="wa-text" rows="6" class="input text-xs">{{ \App\Support\WhatsAppShare::text($post) }}</textarea>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" class="btn bg-[#25d366] text-white !px-3 !py-1.5 text-xs" data-copy="#wa-text">Copy for WhatsApp</button>
                <a href="{{ \App\Support\WhatsAppShare::url($post) }}" target="_blank" rel="noopener" class="btn-secondary !px-3 !py-1.5 text-xs"
                   onclick="this.href='https://wa.me/?text='+encodeURIComponent(document.getElementById('wa-text').value)">Open in WhatsApp</a>
                @if(setting('whatsapp_url'))<a href="{{ setting('whatsapp_url') }}" class="btn-secondary !px-3 !py-1.5 text-xs" onclick="return vdCopyAndOpen(document.getElementById('wa-text').value, this.href, this)">Copy &amp; open channel</a>@endif
                <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs" onclick="vdShare(document.getElementById('wa-text').value)">Share…</button>
            </div>
        </section>
        @endif

        {{-- X (Twitter): free one-click share via the X composer --}}
        @if($post->exists && $post->isPublished())
        <section class="card p-5">
            <h2 class="mb-1 flex items-center gap-2 text-lg font-bold"><svg class="h-5 w-5" viewBox="0 0 24 24"><rect width="24" height="24" rx="5" fill="#000"/><path d="M6 6l12 12M18 6L6 18" stroke="#fff" stroke-width="2"/></svg> X (Twitter)</h2>
            <p class="mb-3 text-xs text-ink-500">On a phone this opens the share menu – choose the <strong>X</strong> app and the post is ready. On a computer it opens x.com. Edit the text first if you like.</p>
            <textarea id="x-text" rows="5" class="input text-xs">{{ \App\Support\XShare::text($post) }}</textarea>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ \App\Support\XShare::url($post) }}" target="_blank" rel="noopener" class="btn bg-black text-white !px-3 !py-1.5 text-xs"
                   onclick="return vdShareX(document.getElementById('x-text').value)">Post on X</a>
                <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs" data-copy="#x-text">Copy text</button>
            </div>
        </section>
        @endif

        {{-- Instagram --}}
        @if($post->exists)
        <section class="card p-5">
            <h2 class="mb-1 flex items-center gap-2 text-lg font-bold"><svg class="h-5 w-5" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" fill="#e1306c"/><circle cx="12" cy="12" r="4.5" fill="none" stroke="#fff" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.3" fill="#fff"/></svg> Instagram</h2>
            <p class="mb-3 text-xs text-ink-500">Post this story to <strong>{{ '@'.ltrim(setting('instagram_username', 'viraldose_news'), '@') }}</strong> as a news card with one click.</p>
            <a href="{{ route('admin.posts.share.card', [$post, 'preview' => 1]) }}" target="_blank" class="block overflow-hidden rounded-lg border border-ink-100 bg-ink-100" title="Open the generated card">
                <img src="{{ route('admin.posts.share.card', [$post, 'preview' => 1, 'v' => $post->updated_at?->timestamp]) }}" alt="Instagram card preview" loading="lazy" class="mx-auto max-h-72 w-auto" onerror="this.style.display='none';this.nextElementSibling.classList.remove('hidden')">
                <span class="hidden px-4 py-8 text-center text-xs text-ink-500">The card preview could not be generated. Check that the PHP GD extension is enabled (and that <code>storage</code> is writable) – see storage/logs/laravel.log.</span>
            </a>
            <p class="mt-1 text-right text-[11px]"><a href="{{ route('admin.posts.share.card', [$post, 'preview' => 1, 'refresh' => 1]) }}" target="_blank" class="text-ink-500 underline">Regenerate card</a></p>
            <label class="label mt-3" for="ig-caption">Caption</label>
            <textarea id="ig-caption" name="caption" form="form-instagram" rows="5" class="input text-xs">{{ old('caption', $instagram->caption($post)) }}</textarea>
            <div class="mt-3 flex flex-wrap gap-2">
                @if($instagram->isReady())
                    <button type="submit" form="form-instagram" name="media" value="card" class="btn bg-[#e1306c] text-white hover:bg-[#c1275a] !px-3 !py-1.5 text-xs">Post card to Instagram</button>
                    @if($post->image)<button type="submit" form="form-instagram" name="media" value="image" class="btn-outline !px-3 !py-1.5 text-xs">Post featured image</button>@endif
                    @if(($v = $post->video()) && $v['type'] === 'file')<button type="submit" form="form-instagram" name="media" value="reel" class="btn-outline !px-3 !py-1.5 text-xs">Post as Reel</button>@endif
                @else
                    <a href="{{ route('admin.settings.edit', ['tab' => 'instagram']) }}" class="btn-outline !px-3 !py-1.5 text-xs">Connect Instagram for one-click posting</a>
                @endif
                <a href="{{ route('admin.posts.share.card', $post) }}" class="btn-secondary !px-3 !py-1.5 text-xs">Download card</a>
                <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs" data-copy="#ig-caption">Copy caption</button>
            </div>
            @php $facebook = app(\App\Services\FacebookPublisher::class); @endphp
            <div class="mt-4 border-t border-ink-100 pt-3">
                <h3 class="flex items-center gap-2 text-sm font-bold"><svg class="h-4 w-4" viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#1877f2"/><path d="M13.2 19v-6h2l.3-2.4h-2.3V9.1c0-.7.2-1.2 1.2-1.2h1.2V5.8c-.2 0-1-.1-1.8-.1-1.8 0-3 1.1-3 3.1v1.8h-2V13h2v6z" fill="#fff"/></svg> Facebook Page</h3>
                @if($facebook->isReady())
                    <textarea name="fb_caption" form="form-facebook" rows="4" class="input mt-2 text-xs">{{ $facebook->caption($post) }}</textarea>
                    <button type="submit" form="form-facebook" class="btn mt-2 bg-[#1877f2] text-white !px-3 !py-1.5 text-xs" @disabled(! $post->isPublished())>Post card to Facebook</button>
                    @unless($post->isPublished())<p class="mt-1 text-[11px] text-ink-500">Publish the post first – the link must work.</p>@endunless
                @else
                    <a href="{{ route('admin.settings.edit', ['tab' => 'instagram']) }}" class="btn-outline mt-2 !px-3 !py-1.5 text-xs">Add Facebook Page ID</a>
                @endif
            </div>
            @if($shares->isNotEmpty())
                <ul class="mt-3 divide-y divide-ink-100 border-t border-ink-100 text-xs">
                    @foreach($shares as $share)
                        <li class="flex items-center gap-2 py-1.5">
                            <span class="{{ ['published' => 'badge-green', 'processing' => 'badge-blue', 'failed' => 'badge-red'][$share->status] ?? 'badge-gray' }}">{{ $share->status }}</span>
                            <span class="text-ink-500">{{ ucfirst($share->network) }} {{ $share->media_type }} · {{ $share->created_at->diffForHumans() }}</span>
                            @if($share->permalink)<a href="{{ $share->permalink }}" target="_blank" class="text-brand-600 underline">open</a>@endif
                            @if($share->status === 'processing')<button type="submit" form="form-share-check-{{ $share->id }}" class="text-brand-600 underline">check</button>@endif
                            @if($share->status === 'failed')<span class="truncate text-red-600" title="{{ $share->response }}">{{ \Illuminate\Support\Str::limit($share->response, 60) }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
            <p class="mt-3 text-xs text-ink-500">Reels: <a href="{{ route('admin.reels.create', ['post' => $post->id]) }}" class="text-brand-600 underline">create a reel from this story</a>.</p>
        </section>
        @endif

        {{-- Category --}}
        <section class="card p-5">
            <h2 class="mb-3 text-lg font-bold">Category</h2>
            <x-admin.select label="Language" name="language" :value="$post->language ?: 'en'" :options="$languages" />
            <div class="mb-4">
                <label class="label" for="f-category_id">Category</label>
                <select id="f-category_id" name="category_id" class="input" data-category-select>
                    <option value="">Select a category</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" data-slug="{{ $c->slug }}" @selected((string) old('category_id', $post->category_id) === (string) $c->id)>{{ $c->name }}</option>
                        @foreach($c->children as $ch)
                            <option value="{{ $ch->id }}" data-slug="{{ $ch->slug }}" @selected((string) old('category_id', $post->category_id) === (string) $ch->id)>— {{ $ch->name }}</option>
                        @endforeach
                    @endforeach
                </select>
                @error('category_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </section>

        {{-- Image --}}
        <section class="card p-5">
            <h2 class="text-lg font-bold">Image</h2>
            <p class="mb-3 text-xs text-ink-500">Main post image</p>
            <x-admin.file name="image" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" button="Select Image" :preview="$post->imageUrl('medium')" preview-class="aspect-video" help="JPG / PNG / WebP / AVIF up to 5 MB · 1200×675 recommended · WebP sizes generated automatically" />
            <x-admin.field label="or Add Image Url" name="image_url" type="url" :value="\Illuminate\Support\Str::startsWith($post->image, 'http') ? $post->image : ''" class="mt-3" />
            <x-admin.field label="Image Description (alt text)" name="image_alt" :value="$post->image_alt" :max="200" help="Describe the image for accessibility and Google Images." />
            <x-admin.field label="Caption" name="image_caption" :value="$post->image_caption" :max="300" />
            @if($post->image)<x-admin.checkbox label="Remove current image" name="remove_image" />@endif
            @if($post->exists && $aiImages)
                <div class="mt-3 rounded border border-ink-300/60 p-3">
                    <p class="text-xs font-semibold">No photo? Make an AI news thumbnail</p>
                    <input type="text" name="ai_scene" form="form-ai-image" maxlength="1000" class="input mt-2 !text-xs" placeholder="Optional scene, e.g. oil tanker in the Gulf at dusk, map of Qatar">
                    <button type="submit" form="form-ai-image" class="btn-outline mt-2 !px-3 !py-1.5 text-xs">Generate AI image (headline + scene)</button>
                    <p class="mt-1 text-xs text-ink-500">Save your edits first – this replaces the featured image (takes ~30 s).</p>
                </div>
            @endif
        </section>

        {{-- Additional images --}}
        <section class="card p-5">
            <h2 class="text-lg font-bold">Additional Images</h2>
            <p class="mb-3 text-xs text-ink-500">Photo gallery shown under the article</p>
            @if($post->exists && $post->images->isNotEmpty())
                <ul class="mb-3 grid grid-cols-3 gap-2">
                    @foreach($post->images as $image)
                        <li class="relative">
                            <img src="{{ $image->url('small') }}" alt="" class="aspect-square w-full rounded object-cover">
                            <input type="text" name="image_captions[{{ $image->id }}]" value="{{ $image->caption }}" placeholder="Caption" class="input mt-1 !px-1.5 !py-0.5 !text-xs">
                            <button type="submit" formaction="{{ route('admin.posts.images.destroy', $image) }}" formmethod="post" name="_method" value="DELETE" formnovalidate class="absolute right-1 top-1 rounded-full bg-black/70 p-1 text-white" title="Remove"><x-admin.icon name="x" class="h-3 w-3" /></button>
                        </li>
                    @endforeach
                </ul>
            @endif
            <x-admin.file name="gallery[]" accept="image/*" :multiple="true" button="Select Images" help="Up to 12 images per save, 5 MB each" />
            @error('gallery.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        </section>

        {{-- Files --}}
        <section class="card p-5">
            <h2 class="text-lg font-bold">Files</h2>
            <p class="mb-3 text-xs text-ink-500">Downloadable additional files (.pdf, .docx, .zip etc.)</p>
            @if($post->exists && $post->files->isNotEmpty())
                <ul class="mb-3 divide-y divide-ink-100 text-sm">
                    @foreach($post->files as $file)
                        <li class="flex items-center justify-between gap-2 py-1.5">
                            <a href="{{ $file->url() }}" target="_blank" class="truncate hover:text-brand-600">{{ $file->name }}</a>
                            <span class="shrink-0 text-xs text-ink-500">{{ $file->humanSize() }} · {{ $file->downloads }} downloads</span>
                            <button type="submit" formaction="{{ route('admin.posts.files.destroy', $file) }}" formmethod="post" name="_method" value="DELETE" formnovalidate class="text-red-600" title="Remove"><x-admin.icon name="x" class="h-4 w-4" /></button>
                        </li>
                    @endforeach
                </ul>
            @endif
            <x-admin.file name="files[]" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.txt,.csv" :multiple="true" button="Select Files" icon="file" help="PDF, Word, Excel, PowerPoint, ZIP, TXT, CSV · up to 20 MB each" />
            @error('files.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        </section>
    </div>
</form>

@if($post->exists)
    {{-- Action forms live outside the main form; buttons in the Google card target them via form="…" --}}
    <form id="form-trash" method="post" action="{{ route('admin.posts.destroy', $post) }}" class="hidden" data-confirm="Move this post to trash?">@csrf @method('DELETE')</form>
    <form id="form-inspect" method="post" action="{{ route('admin.posts.inspect', $post) }}" class="hidden">@csrf</form>
    <form id="form-ai-image" method="post" action="{{ route('admin.posts.ai-image', $post) }}" class="hidden" data-confirm="Replace the featured image with an AI-generated thumbnail?">@csrf</form>
    <form id="form-pull-content" method="post" action="{{ route('admin.posts.pull-content', $post) }}" class="hidden" data-confirm="Replace this post's content with the full article from the source page?">@csrf</form>
    <form id="form-index-request" method="post" action="{{ route('admin.posts.index-request', $post) }}" class="hidden">@csrf</form>
    <form id="form-instagram" method="post" action="{{ route('admin.posts.share.instagram', $post) }}" class="hidden">@csrf</form>
    <form id="form-facebook" method="post" action="{{ route('admin.posts.share.facebook', $post) }}" class="hidden">@csrf</form>
    @foreach($shares as $share)<form id="form-share-check-{{ $share->id }}" method="post" action="{{ route('admin.shares.check', $share) }}" class="hidden">@csrf</form>@endforeach
@endif
@include('admin.posts._share-js')
@endsection
