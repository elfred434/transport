<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Lecture des entrées — sémantique identique aux helpers `input_*()` de l'API
 * d'origine (trim, valeurs par défaut, virgule décimale acceptée), afin que les
 * messages d'erreur et les comportements de validation restent inchangés.
 */
final class In
{
    /** Équivalent de input_str() : chaîne trimmée, défaut si absente/non chaîne. */
    public static function str(Request $request, string $key, string $default = ''): string
    {
        $v = $request->input($key, $default);

        return is_string($v) ? trim($v) : ($v === null ? '' : $default);
    }

    /** Équivalent de input_int(). */
    public static function int(Request $request, string $key, int $default = 0): int
    {
        $v = $request->input($key, $default);

        return is_numeric($v) ? (int) $v : $default;
    }

    /** Équivalent de input_float() : accepte '3,5' comme '3.5'. */
    public static function float(Request $request, string $key, float $default = 0.0): float
    {
        $v = $request->input($key, $default);
        if (is_string($v)) {
            $v = str_replace(',', '.', $v);
        }

        return is_numeric($v) ? (float) $v : $default;
    }

    /** Équivalent de query_str() : paramètre de query string trimmé. */
    public static function queryStr(Request $request, string $key, string $default = ''): string
    {
        $v = $request->query($key, $default);

        return is_string($v) ? trim($v) : $default;
    }

    /** Équivalent de query_int(). */
    public static function queryInt(Request $request, string $key, int $default = 0): int
    {
        $v = $request->query($key, $default);

        return is_numeric($v) ? (int) $v : $default;
    }
}
