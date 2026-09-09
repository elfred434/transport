<?php

namespace App\Support;

use App\Exceptions\ApiException;
use Illuminate\Http\UploadedFile;

/**
 * Upload d'images — mêmes garanties que l'API d'origine :
 * liste blanche de types vérifiée sur le contenu réel (MIME), taille limitée,
 * nom de fichier aléatoire, stockage hors racine web.
 */
final class Uploads
{
    /** Extensions autorisées, indexées par type MIME réel. */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Valide et stocke une image.
     *
     * @return string|null chemin relatif 'uploads/...' tel que stocké en base,
     *                     ou null si aucun fichier fourni (champ optionnel)
     */
    public static function storeImage(?UploadedFile $file, string $subdir = '', string $prefix = 'file'): ?string
    {
        if ($file === null) {
            return null;
        }
        if (! $file->isValid()) {
            throw new ApiException("Erreur lors de l'upload du fichier (code {$file->getError()})");
        }

        $maxBytes = (int) config('transport.max_upload_size', 5 * 1024 * 1024);
        if ($file->getSize() > $maxBytes) {
            throw new ApiException('Fichier trop lourd (maximum ' . intdiv($maxBytes, 1048576) . ' Mo)');
        }

        // Le type MIME est déterminé sur le contenu, jamais sur le nom/extension.
        $mime = $file->getMimeType();
        if (! isset(self::ALLOWED[$mime])) {
            throw new ApiException('Type de fichier non autorisé (images JPEG, PNG, GIF ou WEBP uniquement)');
        }

        $subdir = trim($subdir, '/');
        $directory = 'uploads' . ($subdir !== '' ? '/' . $subdir : '');
        $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . self::ALLOWED[$mime];

        $stored = $file->storeAs($directory, $name, ['disk' => 'transport']);
        if ($stored === false) {
            throw new ApiException('Échec du déplacement du fichier uploadé', 500);
        }

        return $directory . '/' . $name;
    }
}
