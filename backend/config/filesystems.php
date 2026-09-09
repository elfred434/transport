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
        | Racine du stockage des fichiers uploadés (dossier uploads/ sous la
        | racine). Les fichiers historiques de l'API d'origine y ont été déplacés
        | tels quels : aucune URL ni valeur en base n'est modifiée.
        | En production, fixez TRANSPORT_STORAGE_PATH vers un emplacement dédié
        | (ex. /var/www/transport-storage) hors du dépôt.
        |
        | Ce disque n'est jamais servi directement par un serveur web : la
        | distribution passe par une route dédiée qui valide le chemin réel
        | (garde anti path-traversal) et force le Content-Type.
        |
        */

        'transport' => [
            'driver' => 'local',
            'root' => env('TRANSPORT_STORAGE_PATH', storage_path()),
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
