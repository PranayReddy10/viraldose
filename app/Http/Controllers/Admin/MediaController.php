<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageService;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(private ImageService $images) {}

    /**
     * Used by the rich-text editor to upload inline images.
     */
    public function upload(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:5120']]);
        $path = $this->images->store($request->file('file'), 'uploads/content');

        return response()->json([
            'url' => ImageService::url($path, 'large'),
            'path' => $path,
        ]);
    }
}
