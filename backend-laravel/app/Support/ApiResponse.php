<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Contrat de réponse de l'API — strictement identique à l'API PHP d'origine,
 * afin que les suites de tests existantes (tests/test_api.py,
 * tests/test_frontend_integration.js) servent de contrat d'acceptation.
 *
 * Succès : {"success": true,  "data": ...}
 * Erreur : {"success": false, "error": "message lisible"}
 */
final class ApiResponse
{
    public static function success(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(
            ['success' => true, 'data' => $data],
            $status,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    public static function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json(
            ['success' => false, 'error' => $message],
            $status,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
