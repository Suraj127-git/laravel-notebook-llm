<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class ApiAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Check if user is authenticated via Sanctum
        if ($request->user()) {
            return $next($request);
        }

        // Return 401 for API routes instead of redirecting
        return response()->json([
            'error' => 'Authentication required',
            'message' => 'Please provide a valid API token'
        ], 401);
    }
}
