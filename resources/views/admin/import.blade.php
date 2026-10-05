@extends('layouts.admin')
@section('title', 'Import old posts')
@section('content')
<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <section class="card p-5">
            <h2 class="font-bold">1. Upload the Varient export</h2>
            <p class="mt-1 text-sm text-ink-700">In the <em>old</em> site's phpMyAdmin select the <code>posts</code> table → Export → SQL (also <code>categories</code>, <code>images</code>, <code>users</code> if you can – you may upload several files). Max 200 MB per file.</p>
            <form method="post" action="{{ route('admin.import.upload') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-2">
                @csrf
                <input type="file" name="file" accept=".sql,.txt" required class="text-sm">
                <button class="btn-primary">Upload</button>
            </form>
            @if($uploaded->isNotEmpty())
                <table class="table-admin mt-4"><thead><tr><th>File</th><th>Size</th><th>Uploaded</th><th></th></tr></thead><tbody>
                    @foreach($uploaded as $f)
                        <tr><td class="font-mono text-xs">{{ $f['name'] }}</td><td>{{ round($f['size'] / 1048576, 2) }} MB</td><td class="text-ink-500">{{ \Illuminate\Support\Carbon::createFromTimestamp($f['at'])->diffForHumans() }}</td><td class="text-right"><x-admin.delete-button :action="route('admin.import.destroy', $f['name'])" /></td></tr>
                    @endforeach
                </tbody></table>
            @endif
        </section>

        <section class="card p-5">
            <h2 class="font-bold">2. Run the import</h2>
            @if($uploaded->isEmpty())
                <p class="mt-1 text-sm text-ink-500">Upload a file first.</p>
            @else
                <form method="post" action="{{ route('admin.import.run') }}" class="mt-3 space-y-4">
                    @csrf
                    <x-admin.select label="SQL file" name="file" :options="$uploaded->pluck('name', 'name')" />
                    <div>
                        <label class="label">Mode</label>
                        <label class="mr-4 inline-flex items-center gap-1 text-sm"><input type="radio" name="mode" value="dry" checked> Dry run (preview counts, nothing written)</label>
                        <label class="mr-4 inline-flex items-center gap-1 text-sm"><input type="radio" name="mode" value="import"> Import</label>
                        <label class="inline-flex items-center gap-1 text-sm"><input type="radio" name="mode" value="update"> Update categories of already-imported posts</label>
                    </div>
                    <x-admin.select label="Default category (for unmapped legacy ids)" name="default_category" value="news" :options="$categories->pluck('name', 'slug')" />
                    <x-admin.field label="Category map override" name="category_map" :value="collect($map)->map(fn ($slug, $id) => $id.':'.$slug)->implode(', ')" help="Old category id → new category slug, comma separated. Unknown slugs are created. Leave as is to use the built-in viraldose.in mapping." />
                    <x-admin.checkbox label="Also start downloading featured images during the import (first minute; the rest continues in step 3)" name="download_images" :checked="true" />
                    <button class="btn-primary">Run</button>
                    <p class="text-xs text-ink-500">Safe to run again: posts are matched by their old id and never duplicated. Large imports may take a few minutes – keep the tab open.</p>
                </form>
            @endif
        </section>

        <section class="card p-5">
            <h2 class="font-bold">3. Download remote images</h2>
            <p class="mt-1 text-sm text-ink-700">Imported posts whose featured image is still a link to another website are copied into your own storage (sites like Times of India block hot-linked images). Runs 15 posts per click; the scheduler also does this automatically every 5 minutes.</p>
            <p class="mt-2 text-sm"><strong>{{ number_format($remoteImages) }}</strong> waiting · <strong>{{ number_format($failedImages) }}</strong> failed</p>
            <form method="post" action="{{ route('admin.import.fetch-images') }}" class="mt-3 flex flex-wrap gap-2">
                @csrf
                <button class="btn-primary" @disabled($remoteImages === 0)>Download next 15</button>
                @if($failedImages > 0)<button name="retry" value="1" class="btn-outline">Retry failed</button>@endif
            </form>
        </section>

        @if($output)
            <section class="card p-5">
                <h2 class="font-bold">Result</h2>
                <pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap rounded bg-ink-900 p-4 text-xs text-green-200">{{ $output }}</pre>
            </section>
        @endif
    </div>
    <aside class="space-y-6">
        <section class="card p-5 text-sm">
            <h2 class="font-bold">Status</h2>
            <p class="mt-2"><span class="text-3xl font-black">{{ number_format($imported) }}</span><br><span class="text-ink-500">posts imported from the old site so far</span></p>
            <a href="{{ route('admin.posts.index') }}" class="mt-2 inline-block text-brand-600 underline">Open posts</a>
        </section>
        <section class="card p-5 text-sm">
            <h2 class="font-bold">What gets migrated</h2>
            <ul class="mt-2 list-disc space-y-1 pl-4 text-ink-700">
                <li>Title, slug (old links keep working via 301), summary, content (cleaned), keywords → tags</li>
                <li>Category (mapped), publish date, page views, featured image, video embeds</li>
                <li>Drafts / hidden posts stay unpublished</li>
            </ul>
            <p class="mt-3 text-xs text-ink-500">Command-line equivalent: <code>php artisan import:varient-sql file.sql --download-images</code></p>
        </section>
    </aside>
</div>
@endsection
