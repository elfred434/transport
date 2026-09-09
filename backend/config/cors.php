<?php

/**
 * CORS — le frontend est servi depuis une origine différente de l'API.
 *
 * `CORS_ORIGINS` accepte '*' (comportement par défaut, identique à l'API
 * d'origine) ou une liste d'origines séparées par virgules.
 *
 * Note de sécurité : l'authentification repose sur un jeton Bearer envoyé dans
 * un en-tête, jamais sur un cookie. `supports_credentials` reste donc à false,
 * ce qui rend l'usage de '*' sans danger (un tiers ne peut pas rejouer une
 * session à l'insu du navigateur).
 */

$origines = env('CORS_ORIGINS', '*');
$autoriserTout = trim($origines) === '*';

return [

    'paths' => ['api/*', 'uploads/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $autoriserTout
        ? ['*']
        : array_values(array_filter(array_map('trim', explode(',', $origines)))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
