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
        // API sans état : pas de session ni de cookie CSRF.
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: ['api/*']);

        // Alias utilisés par les routes de l'API.
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // API pure : un visiteur non authentifié reçoit un 401 JSON (rendu par
        // le handler ci-dessous), jamais une redirection vers une page 'login'
        // qui n'existe pas dans cette application.
        $middleware->redirectGuestsTo(fn () => null);

        // CORS : le middleware HandleCors est déjà enregistré dans le groupe
        // global et lit config/cors.php (origines pilotées par CORS_ORIGINS).
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Toute l'API répond en JSON, y compris les erreurs.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Erreurs métier explicites : message lisible + code HTTP choisi.
        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error($e->getMessage(), $e->getStatus());
            }
        });

        // Validation : on ne renvoie que le premier message, comme l'API d'origine.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(collect($e->errors())->flatten()->first() ?? 'Données invalides', 422);
            }
        });

        // 401 (jeton absent/invalide) -> contrat {"success":false,"error":...}
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

        // Toute autre exception : message générique, détails en logs seulement.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                report($e);

                return ApiResponse::error('Erreur interne du serveur', 500);
            }
        });
    })->create();
