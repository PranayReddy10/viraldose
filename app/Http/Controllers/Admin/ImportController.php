<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Services\RemoteImageFetcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Admin → Tools → Import: run the Varient SQL importer from the browser
 * (for shared hosting without SSH).
 */
class ImportController extends Controller
{
    public function index()
    {
        $uploaded = collect(Storage::disk('local')->files('imports'))
            ->filter(fn ($f) => str_ends_with(strtolower($f), '.sql'))
            ->map(fn ($f) => ['name' => basename($f), 'size' => Storage::disk('local')->size($f), 'at' => Storage::disk('local')->lastModified($f)])
            ->sortByDesc('at')->values();

        return view('admin.import', [
            'uploaded' => $uploaded,
            'imported' => Post::withTrashed()->whereNotNull('legacy_id')->count(),
            'categories' => Category::ordered()->get(['id', 'name', 'slug', 'parent_id']),
            'map' => config('varient-import.category_map', []),
            'output' => session('import_output'),
            'remoteImages' => RemoteImageFetcher::pendingQuery()->whereNull('image_fetch_error')->count(),
            'failedImages' => RemoteImageFetcher::pendingQuery()->whereNotNull('image_fetch_error')->count(),
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:204800']]);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['sql', 'txt'], true)) {
            return back()->withErrors(['file' => 'Upload a .sql export (phpMyAdmin → Export → posts table).']);
        }
        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', $file->getClientOriginalName());
        Storage::disk('local')->putFileAs('imports', $file, $name);

        return back()->with('status', "Uploaded {$name}. Now run the import below.");
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]+$/'],
            'mode' => ['required', 'in:dry,import,update'],
            'download_images' => ['nullable', 'boolean'],
            'default_category' => ['nullable', 'string', 'exists:categories,slug'],
            'category_map' => ['nullable', 'string', 'max:2000', 'regex:/^(\s*\d+\s*:\s*[a-z0-9-]+\s*,?)*$/i'],
        ]);
        $path = Storage::disk('local')->path('imports/'.$data['file']);
        abort_unless(is_file($path), 404);

        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $options = [
            'file' => $path,
            '--default-category' => ($data['default_category'] ?? null) ?: 'news',
            '--category-map' => $data['category_map'] ?? '',
            '--no-interaction' => true,
            '--no-ansi' => true,
        ];
        if ($data['mode'] === 'dry') {
            $options['--dry-run'] = true;
        }
        if ($data['mode'] === 'update') {
            $options['--update'] = true;
        }
        if ($request->boolean('download_images') && $data['mode'] !== 'dry') {
            $options['--download-images'] = true;
        }

        try {
            $code = Artisan::call('import:varient-sql', $options);
            $output = trim(preg_replace('/\s*\d+\/\d+\s*\[[^\]]*\]\s*\d+%\s*/', '', Artisan::output()));
            $output = ($code === 0 ? "✔ Finished\n\n" : "✖ Failed (exit {$code})\n\n").$output;
        } catch (\Throwable $e) {
            $output = '✖ Error: '.$e->getMessage();
        }
        Artisan::call('optimize:clear');

        return redirect()->route('admin.import.index')->with('import_output', $output);
    }

    public function fetchImages(Request $request, RemoteImageFetcher $fetcher)
    {
        @set_time_limit(300);
        $result = $fetcher->run(15, $request->boolean('retry'), 90);
        $output = "Downloaded {$result['done']}, failed {$result['failed']}, remaining {$result['remaining']}.\n\n".implode("\n", $result['lines']);
        Post::flushCache();

        return redirect()->route('admin.import.index')->with('import_output', $output);
    }

    public function destroy(string $file)
    {
        abort_unless(preg_match('/^[A-Za-z0-9._-]+$/', $file), 404);
        Storage::disk('local')->delete('imports/'.$file);

        return back()->with('status', 'File deleted.');
    }
}
