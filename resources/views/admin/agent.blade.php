@extends('layouts.admin')
@section('title', 'Content Agent')
@section('content')
<div class="grid max-w-5xl gap-6 lg:grid-cols-2">
    <div class="card p-6">
        <h2 class="text-lg font-bold">Agent API</h2>
        <p class="mt-1 text-sm text-ink-700">A scheduled Claude task writes articles and sends them here. Articles arrive as <strong>drafts</strong> to review under Posts – or go live immediately when “Publish directly” is on.</p>

        @if(session('agent_token'))
            <div class="mt-4 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                <p class="font-semibold">Your new API token (shown only once):</p>
                <p class="mt-2 select-all break-all rounded bg-white p-2 font-mono text-xs text-ink-900">{{ session('agent_token') }}</p>
            </div>
        @endif

        <p class="mt-4 text-sm">Status:
            @if($enabled && $hasToken)<strong class="text-green-800">Active</strong>@else<strong class="text-red-700">Off</strong>@endif
            @if($hasToken && $tokenCreatedAt)<span class="text-xs text-ink-500"> · token created {{ $tokenCreatedAt }}</span>@endif
        </p>

        <form method="post" action="{{ route('admin.agent.update') }}" class="mt-4">
            @csrf @method('PUT')
            <x-admin.checkbox label="Allow the agent to create draft posts" name="agent_enabled" :checked="$enabled" />
            <x-admin.checkbox label="Publish directly (skip review)" name="agent_auto_publish" :checked="$autoPublish" help="Agent articles go live immediately, are sent to IndexNow and auto-shared to Instagram (if on). Leave off to review drafts first." />
            <x-admin.select label="Credit drafts to" name="agent_user_id" :value="$authorId" placeholder="First admin"
                :options="$authors->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->role.')'])->all()" />
            <button class="btn-primary">Save</button>
        </form>

        <div class="mt-6 flex flex-wrap gap-3 border-t border-ink-300/60 pt-4">
            <form method="post" action="{{ route('admin.agent.token') }}">@csrf
                <button class="btn-outline">{{ $hasToken ? 'Create a new token (replaces the old one)' : 'Create API token' }}</button>
            </form>
            @if($hasToken)
                <form method="post" action="{{ route('admin.agent.revoke') }}">@csrf @method('DELETE')
                    <button class="btn-outline text-red-700">Revoke token</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card p-6 text-sm">
        <h2 class="text-lg font-bold">Endpoints</h2>
        <p class="mt-1 text-ink-700">Header on every call: <code class="font-mono text-xs">Authorization: Bearer &lt;token&gt;</code></p>
        <ul class="mt-3 space-y-2">
            <li><code class="font-mono text-xs">GET {{ url('/api/agent/context') }}</code><br><span class="text-xs text-ink-500">Categories, the latest 100 headlines (for internal links) and drafts waiting for review.</span></li>
            <li><code class="font-mono text-xs">POST {{ url('/api/agent/posts') }}</code><br><span class="text-xs text-ink-500">JSON: title, category (slug), content (HTML, 400+ words), excerpt, tags[], meta_title, meta_description, meta_keywords, image_base64, image_kind (photo|card), image_alt, image_caption (photo credit). Links to other websites are removed automatically.</span></li>
            <li><code class="font-mono text-xs">GET {{ url('/api/agent/posts') }}</code><br><span class="text-xs text-ink-500">Posts created by the agent and their status.</span></li>
        </ul>
    </div>
</div>

<form method="post" action="{{ route('admin.agent.plan') }}" class="card mt-6 max-w-5xl p-6">
    @csrf @method('PUT')
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-lg font-bold">Daily plan</h2>
        <span class="text-sm text-ink-500">Today ({{ $progress['date'] }}): <strong>{{ $progress['remaining_total'] }}</strong> articles still to write</span>
    </div>
    <p class="mt-1 text-sm text-ink-700">The agent runs several times a day and keeps going until today's plan is complete. <strong>Top news</strong> = the biggest stories of the day in any category (saved as featured). Category numbers are extra articles for that category. Quality beats volume for Google – start small and raise it once posts get indexed.</p>

    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <x-admin.field label="Top news per day (any category)" name="agent_top_news" type="number" :value="$topNews" help="Done today: {{ $progress['top_news']['created_today'] }} of {{ $progress['top_news']['target'] }}" />
        <x-admin.field label="Max articles per run" name="agent_max_per_run" type="number" :value="$maxPerRun" help="The task runs 3× a day; each run writes at most this many." />
    </div>

    <h3 class="mt-2 text-sm font-bold">Articles per category per day</h3>
    @php $done = collect($progress['categories'])->keyBy('slug'); @endphp
    <div class="mt-2 grid gap-2 sm:grid-cols-3">
        @foreach($categories as $category)
            <label class="flex items-center justify-between gap-2 rounded border border-ink-300/60 px-3 py-2 text-sm">
                <span>{{ $category->name }}
                    @if(isset($done[$category->slug]))<span class="block text-xs text-ink-500">today {{ $done[$category->slug]['created_today'] }}/{{ $done[$category->slug]['target'] }}</span>@endif
                </span>
                <input type="number" min="0" max="10" name="quotas[{{ $category->id }}]" value="{{ old('quotas.'.$category->id, $quotas[$category->id] ?? 0) }}" class="input" style="width:5rem">
            </label>
        @endforeach
    </div>
    <button class="btn-primary mt-4">Save plan</button>
</form>

<div class="card mt-6 max-w-5xl overflow-x-auto">
    <table class="table-admin">
        <thead><tr><th>Agent posts</th><th>Category</th><th>Status</th><th>Created</th><th></th></tr></thead>
        <tbody>
        @forelse($drafts as $post)
            <tr>
                <td class="font-semibold">{{ $post->title }}</td>
                <td>{{ $post->category?->name }}</td>
                <td>{{ ucfirst($post->status) }}</td>
                <td class="text-xs text-ink-500">{{ $post->created_at?->diffForHumans() }}</td>
                <td><a href="{{ route('admin.posts.edit', $post) }}" class="text-brand-600 underline">Review</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-ink-500">No agent posts yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
