<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->isSuperAdmin()) {
            throw ApiException::forbidden('Accès réservé au Super Admin');
        }
        return $next($request);
    }
}
