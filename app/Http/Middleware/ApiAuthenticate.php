<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the general read-only /api/v1/* endpoints --
 * deliberately a separate token type from ScimAuthenticate's ScimToken
 * (see ApiToken's docblock for why).
 */
class ApiAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $plaintext = $request->bearerToken();
        $token = $plaintext ? ApiToken::findByPlaintext($plaintext) : null;

        if (! $token) {
            return response()->json(['message' => 'Invalid or missing bearer token.'], 401);
        }

        $token->update(['last_used_at' => now()]);

        return $next($request);
    }
}
