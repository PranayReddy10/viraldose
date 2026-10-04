<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', Comment::STATUS_PENDING);
        $comments = Comment::with('post:id,title,slug,category_id', 'post.category:id,slug')
            ->when(in_array($status, [Comment::STATUS_PENDING, Comment::STATUS_APPROVED, Comment::STATUS_SPAM], true), fn ($q) => $q->where('status', $status))
            ->latest()->paginate(30)->withQueryString();

        $counts = Comment::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('admin.comments.index', compact('comments', 'status', 'counts'));
    }

    public function update(Request $request, Comment $comment)
    {
        $data = $request->validate(['status' => ['required', 'in:pending,approved,spam']]);
        $comment->update($data);

        return back()->with('status', 'Comment marked as '.$data['status'].'.');
    }

    public function destroy(Comment $comment)
    {
        $comment->delete();

        return back()->with('status', 'Comment deleted.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,spam,delete'],
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ]);
        $query = Comment::whereIn('id', $data['ids']);
        match ($data['action']) {
            'approve' => $query->update(['status' => Comment::STATUS_APPROVED]),
            'spam' => $query->update(['status' => Comment::STATUS_SPAM]),
            'delete' => $query->delete(),
        };

        return back()->with('status', 'Comments updated.');
    }
}
