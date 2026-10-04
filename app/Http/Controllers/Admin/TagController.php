<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function index(Request $request)
    {
        $tags = Tag::withCount('posts')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->orderByDesc('posts_count')->orderBy('name')->paginate(50)->withQueryString();

        return view('admin.tags.index', compact('tags'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        Tag::firstOrCreate(['slug' => Str::slug($data['name'])], ['name' => $data['name']]);

        return back()->with('status', 'Tag saved.');
    }

    public function update(Request $request, Tag $tag)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tags', 'slug')->ignore($tag->id)],
        ]);
        $tag->update($data);

        return back()->with('status', 'Tag updated.');
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();

        return back()->with('status', 'Tag deleted.');
    }
}
