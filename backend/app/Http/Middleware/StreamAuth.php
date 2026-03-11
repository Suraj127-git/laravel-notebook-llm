<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class StreamAuth
{
    public function handle(Request $request, Closure $next)
    {
        // If user is already authenticated via normal middleware, continue
        if (auth()->check()) {
            return $next($request);
        }

        // Handle token authentication via query parameter for EventSource
        if ($request->has('token')) {
            $token       = $request->input('token');
            $accessToken = PersonalAccessToken::findToken($token);

            if ($accessToken) {
                $user = $accessToken->tokenable;
                auth()->setUser($user);

                Log::info('stream_auth.success', [
                    'operation' => 'stream_auth',
                    'status'    => 'success',
                    'user_id'   => $user->id,
                    'path'      => $request->path(),
                    'ip'        => $request->ip(),
                ]);

                return $next($request);
            } else {
                Log::warning('stream_auth.failed', [
                    'operation' => 'stream_auth',
                    'status'    => 'invalid_token',
                    'path'      => $request->path(),
                    'ip'        => $request->ip(),
                ]);
            }
        }

        // Return 401 if no authentication
        return response()->json([
            'error'   => 'Authentication required',
            'message' => 'Please provide a valid API token',
        ], 401);
    }
}
