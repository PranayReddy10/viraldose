<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            abort(403);
        }
        if ($roles && ! $user->hasRole(...$roles)) {
            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
