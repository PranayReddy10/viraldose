<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PageRequest;
use App\Models\Page;
use App\Services\HtmlSanitizer;

class PageController extends Controller
{
    public function __construct(private HtmlSanitizer $sanitizer) {}

    public function index()
    {
        return view('admin.pages.index', ['pages' => Page::orderBy('sort_order')->orderBy('title')->get()]);
    }

    public function create()
    {
        return view('admin.pages.form', ['page' => new Page(['is_active' => true, 'show_in_footer' => true])]);
    }

    public function store(PageRequest $request)
    {
        $data = $request->validated();
        $data['content'] = $this->sanitizer->clean($data['content'] ?? '');
        $page = Page::create($data);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page created.');
    }

    public function edit(Page $page)
    {
        return view('admin.pages.form', compact('page'));
    }

    public function update(PageRequest $request, Page $page)
    {
        $data = $request->validated();
        $data['content'] = $this->sanitizer->clean($data['content'] ?? '');
        $page->update($data);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page updated.');
    }

    public function destroy(Page $page)
    {
        $page->delete();

        return redirect()->route('admin.pages.index')->with('status', 'Page deleted.');
    }
}
