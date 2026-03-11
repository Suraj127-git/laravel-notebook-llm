<?php

namespace App\Http\Middleware;

use App\Logging\Processors\RequestIdProcessor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generates a UUID v4 trace ID at the very start of each HTTP request and
 * captures HTTP metadata (method, path, IP) so every log entry for this
 * request is automatically tagged — enabling full trace filtering in Nightwatch.
 *
 * Also echoes the trace ID back in the X-Trace-Id response header so clients
 * can correlate frontend errors with backend log entries.
 */
class InitializeTrace
{
    public function handle(Request $request, Closure $next): Response
    {
        // Honour an upstream trace ID if provided (e.g. from a reverse proxy or frontend)
        $incomingTrace = $request->header('X-Trace-Id');

        if ($incomingTrace && preg_match('/^[0-9a-f\-]{36}$/', $incomingTrace)) {
            RequestIdProcessor::setTraceId($incomingTrace);
        } else {
            RequestIdProcessor::generate();
        }

        RequestIdProcessor::setHttpContext(
            $request->method(),
            $request->path(),
            $request->ip() ?? 'unknown',
        );

        /** @var Response $response */
        $response = $next($request);

        // Return trace ID to caller for client-side correlation
        $response->headers->set('X-Trace-Id', RequestIdProcessor::current() ?? '');

        return $response;
    }
}
