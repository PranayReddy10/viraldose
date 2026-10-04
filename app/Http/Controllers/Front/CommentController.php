<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Post $post)
    {
        abort_unless(setting('comments_enabled') && $post->allow_comments && $post->isPublished(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
            'website' => ['prohibited'], // honeypot
        ]);
        unset($data['website']);

        $recent = Comment::where('ip_address', $request->ip())->where('created_at', '>=', now()->subMinute())->exists();
        if ($recent) {
            return back()->withErrors(['body' => 'Please wait a moment before posting another comment.'])->withInput();
        }

        $post->comments()->create($data + [
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'status' => setting('comments_auto_approve') ? Comment::STATUS_APPROVED : Comment::STATUS_PENDING,
        ]);

        return redirect($post->url().'#comments')->with('status', setting('comments_auto_approve')
            ? 'Your comment has been posted.'
            : 'Thanks! Your comment is awaiting moderation.');
    }
}
