<?php
/**
 * Bootstrap de l'API : connexion BDD, helpers JSON, authentification Bearer,
 * validation, uploads sécurisés.
 */

require_once dirname(__DIR__) . '/config.php';

// ---------------------------------------------------------------------------
// Base de données (connexion unique)
// ---------------------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (Exception $e) {
            error_log('Erreur connexion BDD : ' . $e->getMessage());
            api_error('Erreur de connexion à la base de données', 500);
        }
    }
    return $pdo;
}

// ---------------------------------------------------------------------------
// Erreurs API
// ---------------------------------------------------------------------------
class ApiError extends RuntimeException
{
    private int $status;

    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message);
        $this->status = $status;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}

function api_error(string $message, int $status = 400): void
{
    throw new ApiError($message, $status);
}

function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_success($data = null, int $status = 200): void
{
    json_out(['success' => true, 'data' => $data], $status);
}

// ---------------------------------------------------------------------------
// Entrées
// ---------------------------------------------------------------------------
/** Corps de requête : JSON décodé, sinon $_POST (multipart/form-data). */
function input(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        $cache = is_array($decoded) ? $decoded : [];
    } else {
        $cache = $_POST;
    }
    return $cache;
}

function input_str(string $key, string $default = ''): string
{
    $v = input()[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function input_int(string $key, int $default = 0): int
{
    $v = input()[$key] ?? $default;
    return is_numeric($v) ? (int) $v : $default;
}

function input_float(string $key, float $default = 0.0): float
{
    $v = input()[$key] ?? $default;
    if (is_string($v)) {
        $v = str_replace(',', '.', $v);
    }
    return is_numeric($v) ? (float) $v : $default;
}

/** Paramètre de route ou query string. */
function query_str(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function query_int(string $key, int $default = 0): int
{
    $v = $_GET[$key] ?? $default;
    return is_numeric($v) ? (int) $v : $default;
}

// ---------------------------------------------------------------------------
// Authentification par jeton Bearer
// ---------------------------------------------------------------------------
function bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
        return $m[1];
    }
    return null;
}

/** Utilisateur courant (via jeton Bearer) ou null. */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;

    $token = bearer_token();
    if (!$token) {
        return null;
    }

    $stmt = db()->prepare(
        "SELECT u.*, t.id AS token_id, t.expires_at
         FROM api_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ?
         LIMIT 1"
    );
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }
    if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
        db()->prepare("DELETE FROM api_tokens WHERE id = ?")->execute([$row['token_id']]);
        return null;
    }

    $user = $row;
    return $user;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        api_error('Authentification requise', 401);
    }
    return $user;
}

function require_admin(): array
{
    $user = require_auth();
    if ($user['role'] !== 'admin') {
        api_error('Accès réservé aux administrateurs', 403);
    }
    return $user;
}

function issue_token(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+' . TOKEN_TTL_DAYS . ' days'));
    db()->prepare("INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
        ->execute([$userId, hash('sha256', $token), $expires]);
    return $token;
}

/** Représentation publique d'un utilisateur (sans données sensibles). */
function user_public(array $u): array
{
    return [
        'id' => (int) $u['id'],
        'nom' => $u['nom'],
        'prenom' => $u['prenom'],
        'email' => $u['email'],
        'telephone' => $u['telephone'],
        'photo_url' => file_url($u['photo_profil'] ?? null),
        'role' => $u['role'],
        'date_inscription' => $u['date_inscription'] ?? null,
    ];
}

// ---------------------------------------------------------------------------
// Fichiers uploadés
// ---------------------------------------------------------------------------
/** URL publique d'un fichier stocké (chemin relatif 'uploads/...' en base). */
function file_url(?string $path): ?string
{
    if (!$path) {
        return null;
    }
    // Les anciens enregistrements pointent vers 'uploads/...' ou 'Uploads/...'.
    // Le préfixe est normalisé en minuscule : le gestionnaire de fichiers ne sert
    // que '/uploads/' (sinon l'URL d'un enregistrement 'Uploads/' retournerait 404).
    $path = ltrim(str_replace('\\', '/', $path), '/');
    if (preg_match('#^[Uu]ploads/#', $path)) {
        return APP_URL . '/' . preg_replace('#^[Uu]ploads/#', 'uploads/', $path);
    }
    return APP_URL . '/uploads/' . basename($path);
}

/**
 * Valide et stocke un upload d'image.
 * @return string|null chemin relatif 'uploads/...' (tel que stocké en base)
 */
function handle_image_upload(array $file, string $subdir = '', string $prefix = 'file'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        api_error("Erreur lors de l'upload du fichier (code {$file['error']})");
    }
    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
        api_error('Fichier trop lourd (maximum ' . (MAX_UPLOAD_SIZE / 1048576) . ' Mo)');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        api_error('Type de fichier non autorisé (images JPEG, PNG, GIF ou WEBP uniquement)');
    }

    $subdir = trim($subdir, '/');
    $dir = STORAGE_PATH . '/uploads' . ($subdir !== '' ? '/' . $subdir : '');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        api_error('Impossible de créer le dossier de destination', 500);
    }

    $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        api_error('Échec du déplacement du fichier uploadé', 500);
    }

    return 'uploads' . ($subdir !== '' ? '/' . $subdir : '') . '/' . $name;
}

// ---------------------------------------------------------------------------
// Métier
// ---------------------------------------------------------------------------
function get_admin_id(): ?int
{
    static $id = false;
    if ($id === false) {
        $row = db()->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
        $id = $row ? (int) $row : null;
    }
    return $id;
}

/** L'utilisateur est-il le transporteur affecté à ce colis (via réservation) ? */
function is_transporteur_of_colis(int $colisId, int $userId): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM reservations r
         JOIN voyages v ON v.id = r.voyage_id
         WHERE r.colis_id = ? AND v.user_id = ?"
    );
    $stmt->execute([$colisId, $userId]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Prix d'un colis calculé côté serveur (règle métier historique). */
function calc_prix(float $poids): float
{
    return round(max(1000, 1000 + (1000 * $poids)) * 1.2, 2);
}

function validate_email(string $email): string
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        api_error('Adresse email invalide');
    }
    return $email;
}

function validate_password(string $password): string
{
    if (strlen($password) < 8) {
        api_error('Le mot de passe doit contenir au moins 8 caractères');
    }
    return $password;
}

/** Enrichit une ligne `colis` avec statut de suivi et image_url. */
function colis_public(array $c): array
{
    $c['image_url'] = file_url($c['image_colis'] ?? null);
    unset($c['image_colis']);
    return $c;
}
