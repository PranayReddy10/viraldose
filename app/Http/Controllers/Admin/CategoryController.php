<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::topLevel()->ordered()->with(['children' => fn ($q) => $q->withCount('posts')])->withCount('posts')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.form', ['category' => new Category(['color' => '#dc2626', 'show_in_menu' => true, 'show_on_home' => true, 'is_active' => true]), 'parents' => Category::topLevel()->ordered()->get()]);
    }

    public function store(CategoryRequest $request)
    {
        Category::create($request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'Category created.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', ['category' => $category, 'parents' => Category::topLevel()->ordered()->where('id', '!=', $category->id)->get()]);
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        if ($category->posts()->withTrashed()->exists() || $category->children()->exists()) {
            return back()->withErrors(['category' => 'Move or delete the posts and sub-categories first.']);
        }
        $category->delete();

        return back()->with('status', 'Category deleted.');
    }
}
