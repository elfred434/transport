<?php
/**
 * Configuration centralisée de l'application.
 * Toutes les valeurs peuvent être surchargées par des variables d'environnement
 * (pratique pour la production sans toucher au code).
 */

// --- Base de données ---
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'transport_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// --- Uploads ---
define('UPLOAD_PATH', __DIR__ . '/uploads');       // chemin absolu sur le disque
define('UPLOAD_URL', 'uploads');                    // chemin relatif utilisé en base/HTML
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);         // 5 Mo

// --- SMTP (PHPMailer) : ne JAMAIS coder les identifiants en dur ---
// Renseigner via variables d'environnement SMTP_USER / SMTP_PASS
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM', getenv('SMTP_FROM') ?: (SMTP_USER ?: 'noreply@localhost'));
define('SMTP_FROM_NAME', 'Agence de Transport');

// --- Application ---
define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost:8000', '/'));
define('APP_NAME', 'Agence de Transport de Colis');
