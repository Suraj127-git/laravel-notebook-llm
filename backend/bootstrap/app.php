<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Register stream auth middleware
        $middleware->alias([
            'stream.auth' => \App\Http\Middleware\StreamAuth::class,
        ]);

        // Add global request logging
        $middleware->web(append: [
            \App\Http\Middleware\LogRequests::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\LogRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Request $request, Throwable $e) {
            // Handle RouteNotFoundException for login route
            if ($e instanceof \Symfony\Component\Routing\Exception\RouteNotFoundException) {
                if (str_contains($e->getMessage(), 'Route [login] not defined')) {
                    return response()->json([
                        'error' => 'Authentication required',
                        'message' => 'Please authenticate to access this resource'
                    ], 401);
                }
            }

            return $request->expectsJson()
                ? response()->json([
                    'message' => $e->getMessage(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                ], 500)
                : response()->view('errors.500', [
                    'message' => $e->getMessage(),
                ], 500);
        });
    })->create();
