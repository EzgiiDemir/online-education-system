<?php

namespace App\Http\Middleware;

use App\Models\ScimToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the SCIM API, deliberately separate from the
 * normal session-based `auth` middleware -- a provisioning client is a
 * machine credential, not a logged-in person, and never touches the
 * session/CSRF machinery the rest of the app relies on.
 */
class ScimAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $plaintext = $request->bearerToken();
        $token = $plaintext ? ScimToken::findByPlaintext($plaintext) : null;

        if (! $token) {
            return response()->json([
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
                'status' => '401',
                'detail' => 'Invalid or missing bearer token.',
            ], 401, ['Content-Type' => 'application/scim+json']);
        }

        $token->update(['last_used_at' => now()]);

        return $next($request);
    }
}
