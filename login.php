<?php
require_once __DIR__ . '/functions.php';
$error   = $_GET['error'] ?? '';
$success = $_GET['register'] ?? $_GET['reset'] ?? '';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Agence de Transport de Colis</title>
    
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="card shadow-lg border-0 rounded-4 p-4" style="max-width: 420px; width: 100%;">
            <h2 class="text-center text-primary mb-4"><i class="fa-solid fa-right-to-bracket"></i> Connexion</h2>
            <form action="auth.php" method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <?php if ($error === '1'): ?><div class="alert alert-danger">Email ou mot de passe incorrect.</div><?php endif; ?>
                <?php if ($error === 'invalid_token'): ?><div class="alert alert-danger">Lien de réinitialisation invalide ou expiré.</div><?php endif; ?>
                <?php if ($success === 'success'): ?><div class="alert alert-success">Opération réussie, vous pouvez vous connecter.</div><?php endif; ?>
                <div class="mb-3">
                    <label for="login-email" class="form-label">Email</label>
                    <input type="email" id="login-email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="login-password" class="form-label">Mot de passe</label>
                    <input type="password" id="login-password" name="password" class="form-control" required>
                </div>
                <div class="mb-2 text-end">
                    <a href="reset_request.php" class="text-primary text-decoration-underline">Mot de passe oublié ?</a>
                </div>
                <button type="submit" name="login" class="btn btn-primary w-100 fw-bold">
                    <i class="fa-solid fa-right-to-bracket"></i> Se connecter
                </button>
                <div class="toggle-link text-center mt-3">
                    Pas encore de compte ? <a href="register.php" class="text-primary text-decoration-underline">Inscrivez-vous</a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>