<?php

namespace App\Http\Middleware;

use App\Logging\Processors\RequestIdProcessor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Logs every HTTP request/response with the trace_id injected by InitializeTrace.
 * Does NOT generate its own request ID — that is the responsibility of InitializeTrace.
 */
class LogRequests
{
    // Routes too noisy or irrelevant to log
    private const SKIP_PATHS = ['sanctum/csrf-cookie', 'up', 'telescope'];

    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        if (in_array($request->path(), self::SKIP_PATHS, true)) {
            return $next($request);
        }

        Log::info('http.request', [
            'operation'  => 'http_request',
            'method'     => $request->method(),
            'path'       => $request->path(),
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id'    => $request->user()?->id,
            'trace_id'   => RequestIdProcessor::current(),
        ]);

        try {
            $response = $next($request);

            Log::info('http.response', [
                'operation'   => 'http_response',
                'method'      => $request->method(),
                'path'        => $request->path(),
                'status'      => $response->getStatusCode(),
                'user_id'     => $request->user()?->id,
                'ip'          => $request->ip(),
                'trace_id'    => RequestIdProcessor::current(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $response;

        } catch (\Throwable $e) {
            Log::error('http.exception', [
                'operation'   => 'http_exception',
                'method'      => $request->method(),
                'path'        => $request->path(),
                'user_id'     => $request->user()?->id,
                'ip'          => $request->ip(),
                'trace_id'    => RequestIdProcessor::current(),
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            throw $e;
        }
    }
}
