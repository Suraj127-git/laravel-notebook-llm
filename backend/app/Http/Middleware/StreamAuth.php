<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
            $token = $request->input('token');
            $accessToken = PersonalAccessToken::findToken($token);
            
            if ($accessToken) {
                $user = $accessToken->tokenable;
                auth()->setUser($user);
                
                \Illuminate\Support\Facades\Log::info('Stream token authentication successful', [
                    'user_id' => $user->id,
                    'token_preview' => substr($token, 0, 10) . '...'
                ]);
                
                return $next($request);
            } else {
                \Illuminate\Support\Facades\Log::warning('Invalid stream token provided', [
                    'token_preview' => substr($token, 0, 10) . '...'
                ]);
            }
        }

        // Return 401 if no authentication
        return response()->json([
            'error' => 'Authentication required',
            'message' => 'Please provide a valid API token'
        ], 401);
    }
}
