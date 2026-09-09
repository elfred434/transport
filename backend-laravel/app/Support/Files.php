<?php

namespace App\Support;

/**
 * URLs des fichiers stockés.
 *
 * Les chemins sont persistés en base sous forme relative ('uploads/...').
 * L'URL publique est absolue et pointe vers l'API, qui sert les fichiers.
 */
final class Files
{
    /**
     * URL publique d'un fichier stocké, ou null si aucun fichier.
     *
     * Gère les enregistrements hérités dont le préfixe est 'Uploads/'
     * (majuscule) : le préfixe est normalisé en minuscule, sinon l'URL
     * générée ne correspondrait à aucune route et retournerait 404.
     */
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $base = rtrim((string) config('transport.app_url'), '/');

        if (preg_match('#^[Uu]ploads/#', $path)) {
            return $base . '/' . preg_replace('#^[Uu]ploads/#', 'uploads/', $path);
        }

        return $base . '/uploads/' . basename($path);
    }
}
