<?php
/**
 * Connexion unique à la base de données (PDO).
 * Inclure ce fichier donne accès à la variable $pdo.
 * Idempotent : plusieurs require_once / inclusions n'ouvrent qu'une connexion.
 */

require_once __DIR__ . '/config.php';

if (!isset($GLOBALS['pdo']) || !$GLOBALS['pdo'] instanceof PDO) {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $GLOBALS['pdo'] = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (Exception $e) {
        error_log('Erreur de connexion BDD : ' . $e->getMessage());
        http_response_code(500);
        die('Erreur de connexion à la base de données');
    }
}

$pdo = $GLOBALS['pdo'];
