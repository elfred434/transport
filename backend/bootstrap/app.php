<?php

use App\Exceptions\ApiException;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: ['api/*']);
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error($e->getMessage(), $e->getStatus());
            }
        });
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(collect($e->errors())->flatten()->first() ?? 'Données invalides', 422);
            }
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Authentification requise', 401);
            }
        });
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Ressource introuvable', 404);
            }
        });
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Endpoint inconnu', 404);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    $e->getMessage() ?: 'Erreur HTTP ' . $e->getStatusCode(),
                    $e->getStatusCode()
                );
            }
        });
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                report($e);
                return ApiResponse::error('Erreur interne du serveur', 500);
            }
        });
    })->create();
