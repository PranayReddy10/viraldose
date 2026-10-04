@extends('layouts.admin')
@section('title', 'Comments')
@section('content')
<div class="mb-4 flex gap-2 text-sm">
    @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'spam' => 'Spam', 'all' => 'All'] as $k => $v)
        <a href="{{ route('admin.comments.index', ['status' => $k]) }}" class="rounded-full px-3 py-1 {{ $status === $k ? 'bg-brand-600 text-white' : 'bg-white border border-ink-300' }}">{{ $v }} @if($k !== 'all')({{ $counts[$k] ?? 0 }})@endif</a>
    @endforeach
</div>
<form method="post" action="{{ route('admin.comments.bulk') }}" class="card overflow-x-auto">
    @csrf
    <div class="flex items-center gap-2 border-b border-ink-100 px-3 py-2 text-sm">
        <select name="action" class="input !w-auto !py-1"><option value="approve">Approve</option><option value="spam">Mark spam</option><option value="delete">Delete</option></select>
        <button class="btn-secondary !py-1">Apply to selected</button>
    </div>
    <table class="table-admin">
        <thead><tr><th><input type="checkbox" id="select-all"></th><th>Author</th><th>Comment</th><th>Post</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($comments as $comment)
            <tr>
                <td><input type="checkbox" name="ids[]" value="{{ $comment->id }}"></td>
                <td class="whitespace-nowrap"><span class="font-medium">{{ $comment->name }}</span><span class="block text-xs text-ink-500">{{ $comment->email }}</span><span class="block text-xs text-ink-500">{{ $comment->ip_address }}</span></td>
                <td class="max-w-md"><p class="whitespace-pre-line">{{ $comment->body }}</p><span class="text-xs text-ink-500">{{ $comment->created_at->diffForHumans() }}</span></td>
                <td><a href="{{ $comment->post?->url() }}#comments" target="_blank" class="hover:text-brand-600">{{ \Illuminate\Support\Str::limit($comment->post?->title, 40) }}</a></td>
                <td><span class="badge-{{ ['pending' => 'yellow', 'approved' => 'green', 'spam' => 'red'][$comment->status] ?? 'gray' }}">{{ $comment->status }}</span></td>
                <td class="whitespace-nowrap text-right text-xs font-semibold">
                    @if($comment->status !== 'approved')<button formaction="{{ route('admin.comments.update', $comment) }}" formmethod="post" name="status" value="approved" class="text-green-700 hover:underline">Approve</button>@endif
                    @if($comment->status !== 'spam')<button formaction="{{ route('admin.comments.update', $comment) }}" formmethod="post" name="status" value="spam" class="ml-2 text-ink-500 hover:underline">Spam</button>@endif
                    <button formaction="{{ route('admin.comments.destroy', $comment) }}" formmethod="post" name="_method" value="DELETE" class="ml-2 text-red-600 hover:underline">Delete</button>
                </td>
            </tr>
        @empty<tr><td colspan="6" class="py-8 text-center text-ink-500">Nothing here.</td></tr>@endforelse
        </tbody>
    </table>
    <input type="hidden" name="_method" value="">
</form>
<div class="mt-4">{{ $comments->links() }}</div>
@endsection
