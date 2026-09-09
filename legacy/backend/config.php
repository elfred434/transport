<?php
/**
 * Configuration du backend (API).
 * Valeurs surchargeables par variables d'environnement.
 */

define('BACKEND_ROOT', __DIR__);
define('STORAGE_PATH', __DIR__ . '/storage');

// --- Base de données ---
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'transport_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// --- CORS : origines autorisées pour le frontend (séparées par des virgules) ---
// '*' est acceptable ici car l'API n'utilise pas de cookies d'authentification
// (jeton Bearer uniquement). En production, lister l'origine exacte du frontend.
define('CORS_ORIGINS', getenv('CORS_ORIGINS') ?: '*');

// --- Jetons d'API ---
define('TOKEN_TTL_DAYS', (int) (getenv('TOKEN_TTL_DAYS') ?: 30));

// --- Uploads ---
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);

// --- SMTP ---
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM', getenv('SMTP_FROM') ?: (SMTP_USER ?: 'noreply@localhost'));
define('SMTP_FROM_NAME', 'Agence de Transport');

// --- Application ---
// URL publique du backend : sert à construire les liens envoyés par email
// (réinitialisation de mot de passe) et les chemins de fichiers uploadés.
define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost:8001', '/'));
define('FRONTEND_URL', rtrim(getenv('FRONTEND_URL') ?: 'http://localhost:8000', '/'));
define('APP_NAME', 'Agence de Transport de Colis');
