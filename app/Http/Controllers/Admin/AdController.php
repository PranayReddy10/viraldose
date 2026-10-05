<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdController extends Controller
{
    public function __construct(private ImageService $images) {}

    public function index()
    {
        return view('admin.ads.index', ['ads' => Ad::orderBy('slot')->orderBy('sort_order')->get()]);
    }

    public function create()
    {
        return view('admin.ads.form', ['ad' => new Ad(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $ad = Ad::create($this->validated($request));

        return redirect()->route('admin.ads.index')->with('status', 'Ad created.');
    }

    public function edit(Ad $ad)
    {
        return view('admin.ads.form', compact('ad'));
    }

    public function update(Request $request, Ad $ad)
    {
        $ad->update($this->validated($request, $ad));

        return redirect()->route('admin.ads.index')->with('status', 'Ad updated.');
    }

    public function destroy(Ad $ad)
    {
        $this->images->delete($ad->image);
        $ad->delete();

        return back()->with('status', 'Ad deleted.');
    }

    private function validated(Request $request, ?Ad $ad = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slot' => ['required', Rule::in(array_keys(Ad::SLOTS))],
            'device' => ['required', Rule::in(array_keys(Ad::DEVICES))],
            'pages' => ['required', Rule::in(array_keys(Ad::PAGES))],
            'code' => ['nullable', 'string', 'max:20000'],
            'image' => ['nullable', 'image', 'max:2048'],
            'url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        unset($data['image']);
        if ($request->hasFile('image')) {
            $data['image'] = $this->images->store($request->file('image'), 'uploads/ads');
            if ($ad) {
                $this->images->delete($ad->image);
            }
        }

        return $data;
    }
}
