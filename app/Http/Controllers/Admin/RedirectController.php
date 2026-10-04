<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RedirectController extends Controller
{
    public function index(Request $request)
    {
        $redirects = Redirect::query()
            ->when($request->filled('q'), fn ($q) => $q->where('from_path', 'like', '%'.$request->q.'%')->orWhere('to_path', 'like', '%'.$request->q.'%'))
            ->orderByDesc('hits')->orderByDesc('id')->paginate(50)->withQueryString();

        return view('admin.redirects.index', compact('redirects'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:500'],
            'to_path' => ['required', 'string', 'max:500'],
            'status_code' => ['required', Rule::in([301, 302, 307, 308])],
        ]);
        $data['from_path'] = Redirect::normalizePath($data['from_path']);
        if ($data['from_path'] === Redirect::normalizePath($data['to_path'])) {
            return back()->withErrors(['to_path' => 'Source and destination cannot be the same.'])->withInput();
        }
        Redirect::updateOrCreate(['from_path' => $data['from_path']], $data);

        return back()->with('status', 'Redirect saved.');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2 || str_starts_with(trim($row[0]), '#') || strtolower(trim($row[0])) === 'from') {
                continue;
            }
            $from = Redirect::normalizePath(trim($row[0]));
            $to = trim($row[1]);
            if ($from === '' || $to === '' || $from === Redirect::normalizePath($to)) {
                continue;
            }
            Redirect::updateOrCreate(['from_path' => $from], [
                'to_path' => $to,
                'status_code' => isset($row[2]) && in_array((int) $row[2], [301, 302, 307, 308], true) ? (int) $row[2] : 301,
            ]);
            $count++;
        }
        fclose($handle);

        return back()->with('status', "{$count} redirect(s) imported.");
    }

    public function destroy(Redirect $redirect)
    {
        $redirect->delete();

        return back()->with('status', 'Redirect deleted.');
    }
}
