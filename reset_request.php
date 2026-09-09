<?php
require_once __DIR__ . '/functions.php';
$error = $_GET['error'] ?? '';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe - Agence de Transport de Colis</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="card shadow-lg border-0 rounded-4 p-4" style="max-width: 420px; width: 100%;">
            <h2 class="text-center text-primary mb-4"><i class="fa-solid fa-key"></i> Réinitialisation du mot de passe</h2>
            
            
            
            <form action="auth.php" method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <?php if ($error === 'mail'): ?><div class="alert alert-danger">Impossible d'envoyer l'email pour le moment. Réessayez plus tard.</div><?php endif; ?>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <button type="submit" name="reset_request" class="btn btn-primary w-100 fw-bold">
                    <i class="fa-solid fa-paper-plane"></i> Envoyer le lien de réinitialisation
                </button>
            </form>
            <a href="login.php" class="btn btn-outline-primary w-100 mt-3 fw-bold">
                <i class="fa-solid fa-right-to-bracket"></i> Retour à la connexion
            </a>
        </div>
    </div>
    <script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>