<?php

/**
 * Configuration métier de la plateforme de transport.
 *
 * Ces valeurs reproduisent celles de l'API PHP d'origine afin que le contrat
 * public (URL de fichiers, durée de vie des jetons, limites d'upload) reste
 * inchangé pendant la migration.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | URL publiques
    |--------------------------------------------------------------------------
    |
    | `app_url` sert à construire les URL absolues des fichiers uploadés (le
    | backend les sert sur /uploads/...). `frontend_url` sert aux liens envoyés
    | par email (réinitialisation de mot de passe).
    |
    */

    // URL de base des fichiers uploadés. Vide par défaut = URL relatives
    // ('/uploads/...') : le SPA les consomme via son proxy dev, et la prod les
    // sert sur le même domaine. Renseigner TRANSPORT_APP_URL si l'API est sur
    // un domaine distinct de celui qui affiche les images.
    'app_url' => env('TRANSPORT_APP_URL', ''),

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:8000'),

    /*
    |--------------------------------------------------------------------------
    | Jetons d'API
    |--------------------------------------------------------------------------
    */

    'token_ttl_days' => (int) env('TOKEN_TTL_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */

    'max_upload_size' => (int) env('MAX_UPLOAD_SIZE', 5 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Règles métier
    |--------------------------------------------------------------------------
    */

    // Statuts de suivi autorisés (ordre significatif du cycle de livraison).
    'suivi_statuts' => ['En attente', 'En cours', 'Livré'],

    // Types de colis acceptés.
    'colis_types' => ['alimentaire', 'electronique', 'vetements', 'documents', 'autre'],

    // Opérateurs Mobile Money acceptés.
    'mobile_money_operateurs' => ['mtn', 'moov', 'wave', 'orange'],

    // Statuts des entités modérables.
    'statuts_moderation' => ['en_attente', 'approuve', 'refuse'],

];
