<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the content agent API. Only the SHA-256 hash of the
 * token is stored (setting "agent_token_hash"); the plain token is shown once
 * in Admin → Content Agent.
 */
class AgentToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $hash = (string) Setting::get('agent_token_hash', '');
        $token = (string) $request->bearerToken();

        if ($hash === '' || ! setting('agent_enabled', 0)) {
            return response()->json(['message' => 'The content agent API is disabled. Enable it in Admin → Content Agent.'], 403);
        }
        if ($token === '' || ! hash_equals($hash, hash('sha256', $token))) {
            return response()->json(['message' => 'Invalid or missing API token.'], 401);
        }

        return $next($request);
    }
}
