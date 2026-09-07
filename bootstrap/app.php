<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        apiPrefix: 'api',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum stateful API
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        // Exclure l'API de la vérification CSRF pour les tokens Bearer
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Custom middleware aliases
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);

        // Trust all proxies (useful for reverse proxy deployments)
        $middleware->trustProxies(at: '*');

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Unauthenticated
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Non authentifié.'], 401);
            }
        });

        // Validation errors
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // Not found
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Ressource introuvable.'], 404);
            }
        });

        // Method not allowed
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Méthode HTTP non autorisée.'], 405);
            }
        });
    })->create();
