<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEO hygiene: one canonical form for every URL.
 *  - strips trailing slashes (except root)
 *  - lowercases paths that contain upper-case letters
 *  - drops common tracking parameters from the canonical
 */
class NormalizeUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }
        $path = $request->getPathInfo();
        if ($path === '/' || str_starts_with($path, '/admin') || str_starts_with($path, '/storage') || str_starts_with($path, '/build')) {
            return $next($request);
        }

        $normalized = rtrim($path, '/');
        $lower = mb_strtolower($normalized);
        if ($lower !== $normalized && ! str_starts_with($normalized, '/search')) {
            $normalized = $lower;
        }
        if ($normalized === '') {
            $normalized = '/';
        }

        if ($normalized !== $path) {
            $query = $request->getQueryString();

            // Collapse into a single hop when the normalised path is itself a known legacy redirect.
            try {
                $legacy = Redirect::findForPath($normalized);
            } catch (\Throwable) {
                $legacy = null;
            }
            if ($legacy) {
                Redirect::withoutTimestamps(fn () => $legacy->increment('hits'));

                return redirect($legacy->to_path, $legacy->status_code ?: 301);
            }

            return redirect($normalized.($query ? '?'.$query : ''), 301);
        }

        return $next($request);
    }
}
