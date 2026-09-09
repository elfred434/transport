<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        /*
        |----------------------------------------------------------------------
        | Disque des fichiers uploadés de la plateforme
        |----------------------------------------------------------------------
        |
        | Racine pointant vers le stockage historique (legacy/backend/storage/uploads),
        | à l'emplacement déjà utilisé par l'API d'origine : les fichiers existants
        | restent lisibles et aucun doublon n'est créé pendant la migration.
        | En production, fixez TRANSPORT_STORAGE_PATH vers un emplacement dédié.
        |
        | Ce disque n'est jamais servi directement par un serveur web : la
        | distribution passe par une route dédiée qui valide le chemin réel
        | (garde anti path-traversal) et force le Content-Type.
        |
        */

        'transport' => [
            'driver' => 'local',
            'root' => env('TRANSPORT_STORAGE_PATH', dirname(__DIR__, 2) . '/legacy/backend/storage'),
            'visibility' => 'private',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
