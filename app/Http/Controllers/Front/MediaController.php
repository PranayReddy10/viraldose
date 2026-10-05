<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;

/**
 * Serves files from the local public disk when the `public/storage` symlink is
 * missing or blocked (common on shared hosting). When the symlink works the
 * web server answers first and this route is never reached.
 */
class MediaController extends Controller
{
    public function storage(string $path)
    {
        $path = ltrim($path, '/');
        abort_if($path === '' || str_contains($path, '..') || str_contains($path, "\0"), 404);
        $disk = Storage::disk(ImageService::LOCAL_DISK);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
