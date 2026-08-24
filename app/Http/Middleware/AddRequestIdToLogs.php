<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every log line written during a request (including from a queued job
 * dispatched inside it, and from Laravel's own exception handler) gets a
 * shared request_id, user_id and route context -- without this, tracing
 * "which request caused this specific log line" across a busy production
 * log stream meant grepping timestamps and hoping nothing else happened in
 * the same second. The id is also echoed back as a response header so it
 * can be handed to support ("what's the X-Request-Id on your error?").
 */
class AddRequestIdToLogs
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();

        Log::shareContext([
            'request_id' => $requestId,
            'user_id' => $request->user()?->id,
            'route' => optional($request->route())->getName(),
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
