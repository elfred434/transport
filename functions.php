<?php
/**
 * Fonctions utilitaires de l'application :
 * - session durcie (HttpOnly, SameSite)
 * - échappement HTML (e())
 * - protection CSRF
 * - gardes d'authentification (require_login / require_admin)
 * - upload d'images sécurisé (handle_image_upload)
 * - diverses aides (admin id, url, flash)
 */

require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------------------
// Session durcie (démarrée une seule fois, paramètres appliqués avant)
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------------------------------------------------------------------------
// Échappement HTML — à utiliser pour TOUTE sortie dynamique
// ---------------------------------------------------------------------------
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Champ caché à insérer dans chaque formulaire POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Vérifie le jeton CSRF d'une requête POST (formulaire ou AJAX).
 * En AJAX : envoyer l'en-tête X-CSRF-Token.
 */
function csrf_check(): void
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || str_starts_with($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        if ($isAjax) {
            http_response_code(403);
            exit(json_encode(['success' => false, 'error' => 'Jeton CSRF invalide']));
        }
        http_response_code(403);
        exit('Jeton de sécurité invalide. Revenez en arrière et réessayez.');
    }
}

// ---------------------------------------------------------------------------
// Authentification / autorisation
// ---------------------------------------------------------------------------
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function is_admin(): bool
{
    return (($_SESSION['user_role'] ?? '') === 'admin');
}

/** URL de la page de connexion adaptée au dossier courant (racine ou admin/). */
function login_url(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    return str_contains(dirname($script), 'admin') ? 'login.php' : 'login.php';
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . login_url());
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Accès réservé aux administrateurs.');
    }
}

/** ID de l'administrateur (premier compte ayant le rôle admin). */
function get_admin_id(): ?int
{
    global $pdo;
    static $adminId = false;
    if ($adminId === false) {
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        $id = $stmt->fetchColumn();
        $adminId = $id ? (int) $id : null;
    }
    return $adminId;
}

// ---------------------------------------------------------------------------
// Upload sécurisé d'images
// ---------------------------------------------------------------------------
/**
 * Valide et déplace un fichier uploadé.
 *
 * @param array  $file   Entrée $_FILES['xxx']
 * @param string $subdir Sous-dossier dans uploads/ (ex. 'colis', 'vehicules', 'messages')
 * @param string $prefix Préfixe du nom généré
 * @return string|null   Chemin relatif (ex. 'uploads/colis/abc.jpg') ou null si aucun fichier
 * @throws RuntimeException si le fichier est invalide
 */
function handle_image_upload(array $file, string $subdir = '', string $prefix = 'file'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Erreur lors de l'upload du fichier (code {$file['error']}).");
    }
    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Fichier trop lourd (maximum ' . (MAX_UPLOAD_SIZE / 1048576) . ' Mo).');
    }

    // Whitelist stricte : le type MIME réel décide de l'extension enregistrée.
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Type de fichier non autorisé (images JPEG, PNG, GIF ou WEBP uniquement).');
    }

    $subdir   = trim($subdir, '/');
    $dir      = UPLOAD_PATH . ($subdir !== '' ? '/' . $subdir : '');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossible de créer le dossier de destination.');
    }

    $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException("Échec du déplacement du fichier uploadé.");
    }

    return UPLOAD_URL . ($subdir !== '' ? '/' . $subdir : '') . '/' . $name;
}

// ---------------------------------------------------------------------------
// Divers
// ---------------------------------------------------------------------------
/** Messages flash en session (succès / erreur). */
function flash_set(string $type, string $message): void
{
    $_SESSION[$type] = $message;
}

function flash_get(string $type): ?string
{
    if (!empty($_SESSION[$type])) {
        $msg = $_SESSION[$type];
        unset($_SESSION[$type]);
        return $msg;
    }
    return null;
}

/** URL de base déduite de la requête courante (pour les liens dans les emails). */
function app_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https://' : 'http://') . $host;
}

/**
 * L'utilisateur courant (session) est-il le transporteur affecté à ce colis
 * (via une réservation sur l'un de ses voyages) ?
 */
function is_transporteur_of_colis(int $colisId, int $userId): bool
{
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM reservations r
         JOIN voyages v ON v.id = r.voyage_id
         WHERE r.colis_id = ? AND v.user_id = ?"
    );
    $stmt->execute([$colisId, $userId]);
    return (int) $stmt->fetchColumn() > 0;
}
