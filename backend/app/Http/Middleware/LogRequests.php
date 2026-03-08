<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogRequests
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $requestId = uniqid('req_', true);

        // Skip logging for certain routes to avoid noise
        $skipRoutes = ['sanctum/csrf-cookie', 'up', 'telescope'];
        if (!in_array($request->path(), $skipRoutes)) {
            Log::info('Request started', [
                'request_id' => $requestId,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => $request->user()?->id,
                'has_auth_header' => $request->hasHeader('Authorization'),
                'auth_header_preview' => $request->hasHeader('Authorization') ? substr($request->header('Authorization'), 0, 20) . '...' : null
            ]);
        }

        try {
            $response = $next($request);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if (!in_array($request->path(), $skipRoutes)) {
                Log::info('Request completed', [
                    'request_id' => $requestId,
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'path' => $request->path(),
                    'status' => $response->getStatusCode(),
                    'duration_ms' => $duration,
                    'user_id' => $request->user()?->id,
                    'ip' => $request->ip()
                ]);
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Request failed with exception', [
                'request_id' => $requestId,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'exception' => $e->getMessage(),
                'exception_class' => get_class($e),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip()
            ]);

            throw $e;
        }
    }
}
