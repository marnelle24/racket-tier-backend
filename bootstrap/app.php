<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'broadcasting/auth',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            return '/';
        });

        $middleware->redirectUsersTo(function (\Illuminate\Http\Request $request) {
            if ($request->routeIs('admin.login') || $request->is('admin/login')) {
                return $request->user()?->isAdmin()
                    ? route('admin.dashboard')
                    : '/';
            }

            return '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                Log::warning('Validation failed', ['exception' => $e, 'errors' => $e->errors()]);

                return response()->json([
                    'success' => false,
                    'message' => 'The given data was invalid.',
                    'data' => ['errors' => $e->errors()],
                ], 422);
            }
        });

        // Broadcast channel auth failures (403) with a clear message for Echo/Reverb
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                Log::warning('Access denied', ['exception' => $e]);

                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to access this channel.',
                ], 403);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                Log::error('HTTP exception', ['exception' => $e, 'status' => $e->getStatusCode()]);

                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred.',
                ], $e->getStatusCode());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                Log::info('Unauthenticated request', ['path' => $request->path()]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });
    })->create();
