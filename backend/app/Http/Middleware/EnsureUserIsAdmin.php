<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve une route aux administrateurs.
 *
 * S'applique après `auth:sanctum` : l'utilisateur est donc déjà authentifié.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            throw ApiException::forbidden('Accès réservé aux administrateurs');
        }

        return $next($request);
    }
}
