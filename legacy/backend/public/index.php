<?php
/**
 * Point d'entrée unique de l'API (front controller).
 *
 * - Applique les en-têtes CORS
 * - Sert les fichiers uploadés (/uploads/...)
 * - Route les requêtes /api/... vers les contrôleurs
 *
 * Lancement : php -S 0.0.0.0:8001 backend/public/index.php
 * (avec -t backend/public)
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

// ---------------------------------------------------------------------------
// CORS
// ---------------------------------------------------------------------------
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (CORS_ORIGINS === '*') {
    header('Access-Control-Allow-Origin: *');
} else {
    $allowed = array_map('trim', explode(',', CORS_ORIGINS));
    if ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$path   = rtrim($path, '/') ?: '/';

// ---------------------------------------------------------------------------
// Fichiers uploadés : GET /uploads/...
// ---------------------------------------------------------------------------
if (str_starts_with($path, '/uploads/')) {
    $relative = substr($path, strlen('/uploads/'));
    // Anti path-traversal : pas de '..', chemin réel sous storage/uploads
    $file = realpath(STORAGE_PATH . '/uploads/' . $relative);
    $base = realpath(STORAGE_PATH . '/uploads');
    if ($file && $base && str_starts_with($file, $base) && is_file($file)) {
        $mimes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp',
        ];
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: public, max-age=86400');
        readfile($file);
    } else {
        http_response_code(404);
    }
    exit;
}

// ---------------------------------------------------------------------------
// Contrôleurs
// ---------------------------------------------------------------------------
foreach (glob(dirname(__DIR__) . '/src/controllers/*.php') as $controller) {
    require_once $controller;
}

// ---------------------------------------------------------------------------
// Table de routage : 'METHOD /api/chemin/{param}' => 'fonction'
// ---------------------------------------------------------------------------
$routes = [
    // --- Auth ---
    'POST /api/auth/register'          => 'auth_register',
    'POST /api/auth/login'             => 'auth_login',
    'POST /api/auth/logout'            => 'auth_logout',
    'GET /api/auth/me'                 => 'auth_me',
    'POST /api/auth/reset-request'     => 'auth_reset_request',
    'POST /api/auth/reset-password'    => 'auth_reset_password',

    // --- Profil ---
    'GET /api/profile'                 => 'profile_show',
    'POST /api/profile'                => 'profile_update',
    'GET /api/transporteurs/{id}'      => 'transporteur_show',
    'GET /api/transporteur-stats'      => 'transporteur_stats',

    // --- Colis ---
    'GET /api/colis/mine'              => 'colis_mine',
    'GET /api/colis/available'         => 'colis_available',
    'GET /api/colis/{id}'              => 'colis_show',
    'POST /api/colis'                  => 'colis_create',
    'POST /api/colis/{id}'             => 'colis_update',
    'GET /api/colis/{id}/voyages-compatibles' => 'colis_voyages_compatibles',

    // --- Suivi ---
    'GET /api/suivi/{numero}'          => 'suivi_show',
    'POST /api/suivi'                  => 'suivi_add',

    // --- Voyages ---
    'GET /api/voyages/available'       => 'voyages_available',
    'GET /api/voyages/mine'            => 'voyages_mine',
    'POST /api/voyages'                => 'voyage_create',

    // --- Réservations ---
    'POST /api/reservations'           => 'reservation_create',
    'GET /api/colis/{id}/reservations' => 'reservations_for_colis',
    'GET /api/reservations/recues'     => 'reservations_recues',
    'POST /api/reservations/{id}/action' => 'reservation_action',

    // --- Messagerie utilisateur ↔ utilisateur ---
    'GET /api/conversations'           => 'conversations_list',
    'GET /api/messages'                => 'messages_list',
    'POST /api/messages'               => 'messages_send',

    // --- Messagerie utilisateur ↔ admin ---
    'GET /api/admin-chat'              => 'adminchat_show',
    'POST /api/admin-chat'             => 'adminchat_send',
    'GET /api/admin-chat/conversations' => 'adminchat_conversations',

    // --- Contact (public) + réponses ---
    'POST /api/contact'                => 'contact_send',
    'GET /api/contact/reponses'        => 'contact_reponses',

    // --- Paiements ---
    'GET /api/paiements/colis/{colis_id}' => 'paiement_for_colis',
    'POST /api/paiements/{id}/payer'   => 'paiement_payer',
    'GET /api/paiements/mine'          => 'paiements_mine',

    // --- Avis ---
    'GET /api/transporteurs/{id}/avis' => 'avis_for_transporteur',
    'POST /api/avis'                   => 'avis_create',

    // --- Admin ---
    'GET /api/admin/stats'             => 'admin_stats',
    'GET /api/admin/users'             => 'admin_users_list',
    'POST /api/admin/users'            => 'admin_user_create',
    'DELETE /api/admin/users/{id}'     => 'admin_user_delete',
    'GET /api/admin/colis'             => 'admin_colis_list',
    'POST /api/admin/colis/{id}/statut' => 'admin_colis_statut',
    'DELETE /api/admin/colis/{id}'     => 'admin_colis_delete',
    'POST /api/admin/suivi/{id}/livraison' => 'admin_livraison_decision',
    'DELETE /api/admin/suivi/{id}'     => 'admin_suivi_delete',
    'GET /api/admin/voyages'           => 'admin_voyages_list',
    'POST /api/admin/voyages/{id}/statut' => 'admin_voyage_statut',
    'DELETE /api/admin/voyages/{id}'   => 'admin_voyage_delete',
    'GET /api/admin/transporteurs'     => 'admin_transporteurs_list',
    'DELETE /api/admin/transporteurs/{id}' => 'admin_transporteur_delete',
    'GET /api/admin/paiements'         => 'admin_paiements_list',
    'POST /api/admin/paiements/{id}/statut' => 'admin_paiement_statut',
    'GET /api/admin/avis'              => 'admin_avis_list',
    'POST /api/admin/avis/{id}/statut' => 'admin_avis_statut',
    'DELETE /api/admin/avis/{id}'      => 'admin_avis_delete',
    'GET /api/admin/contact-messages'  => 'admin_contact_list',
    'POST /api/admin/contact-messages/{id}/repondre' => 'admin_contact_repondre',
    'DELETE /api/admin/contact-messages/{id}' => 'admin_contact_delete',
];

// ---------------------------------------------------------------------------
// Dispatch
// ---------------------------------------------------------------------------
try {
    if ($path === '/api' || $path === '/api/') {
        json_success(['name' => APP_NAME . ' — API', 'version' => '2.0', 'endpoints' => count($routes)]);
    }

    foreach ($routes as $route => $handler) {
        [$routeMethod, $routePath] = explode(' ', $route, 2);
        if ($routeMethod !== $method) {
            continue;
        }
        // Transformation {param} en groupe de capture nommé
        $regex = '#^' . preg_replace('/\{([a-z_]+)\}/', '(?P<$1>[^/]+)', $routePath) . '$#';
        if (preg_match($regex, $path, $matches)) {
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            if (!function_exists($handler)) {
                api_error("Handler manquant : $handler", 500);
            }
            $handler($params);
            api_error('Route sans réponse', 500);
        }
    }

    api_error('Endpoint introuvable : ' . $path, 404);
} catch (ApiError $e) {
    json_out(['success' => false, 'error' => $e->getMessage()], $e->getStatus());
} catch (Throwable $e) {
    error_log('API error [' . $method . ' ' . $path . '] : ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    json_out(['success' => false, 'error' => 'Erreur interne du serveur'], 500);
}
