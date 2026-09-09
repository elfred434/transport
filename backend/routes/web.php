<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/*
|--------------------------------------------------------------------------
| Routes hors API
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => '/api',
]));

/*
 * Distribution des fichiers uploadés : GET /uploads/{path}
 *
 * Reprend les garanties du front controller d'origine :
 *  - chemin réel résolu puis vérifié sous la racine du disque (anti path-traversal,
 *    y compris via liens symboliques) ;
 *  - Content-Type déterminé par l'extension, jamais par l'en-tête du client,
 *    avec repli application/octet-stream : un fichier non image n'est jamais
 *    servi comme du HTML (pas d'exécution de script stocké) ;
 *  - pas de directives d'exécution PHP : ce backend ne sert que des octets.
 */
Route::get('uploads/{path}', function (string $path) {
    $disk = Storage::disk('transport');

    // Le paramètre de route est déjà décodé ; on interdit les séquences de
    // remontée avant même la résolution du chemin réel (défense en profondeur).
    if (str_contains($path, '..') || str_contains($path, "\0")) {
        abort(404);
    }

    $relative = 'uploads/' . ltrim($path, '/');

    if (! $disk->exists($relative) || $disk->mimeType($relative) === false) {
        abort(404);
    }

    $absolute = $disk->path($relative);
    $base = realpath($disk->path('uploads'));
    $real = realpath($absolute);

    if ($base === false || $real === false || ! str_starts_with($real, $base . DIRECTORY_SEPARATOR) || ! is_file($real)) {
        abort(404);
    }

    $mimes = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp',
    ];
    $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));

    return (new BinaryFileResponse($real, 200, [
        'Content-Type' => $mimes[$ext] ?? 'application/octet-stream',
        'Cache-Control' => 'public, max-age=86400',
        'X-Content-Type-Options' => 'nosniff',
    ]))->setAutoEtag(false);
})->where('path', '.*')->name('uploads.show');
