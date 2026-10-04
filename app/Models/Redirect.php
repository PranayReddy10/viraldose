<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $fillable = ['from_path', 'to_path', 'status_code', 'hits'];

    public static function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?? $path;
        $path = '/'.ltrim(trim($path), '/');
        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }

        return mb_strtolower(rawurldecode($path));
    }

    public static function findForPath(string $path): ?self
    {
        $normalized = static::normalizePath($path);

        return static::where('from_path', $normalized)->first();
    }
}
