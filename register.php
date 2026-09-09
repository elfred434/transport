<?php
require_once __DIR__ . '/functions.php';
$error = $_GET['error'] ?? '';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Agence de Transport de Colis</title>
    
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="card shadow-lg border-0 rounded-4 p-4" style="max-width: 420px; width: 100%;">
            <h2 class="text-center text-primary mb-4"><i class="fa-solid fa-user-plus"></i> Inscription</h2>
            <form id="register-form" action="auth.php" method="POST" enctype="multipart/form-data" autocomplete="off">
                <?= csrf_field() ?>
                <?php if ($error === 'exists'): ?><div class="alert alert-danger">Cet email est déjà utilisé.</div><?php endif; ?>
                <?php if ($error === 'invalid'): ?><div class="alert alert-danger">Veuillez remplir tous les champs correctement (mot de passe : 8 caractères minimum).</div><?php endif; ?>
                <?php if ($error === 'upload'): ?><div class="alert alert-danger">Photo de profil invalide (JPEG, PNG, GIF ou WEBP, 5 Mo max).</div><?php endif; ?>
                <div class="mb-3">
                    <label for="register-nom" class="form-label">Nom</label>
                    <input type="text" id="register-nom" name="nom" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="register-prenom" class="form-label">Prénom</label>
                    <input type="text" id="register-prenom" name="prenom" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="register-email" class="form-label">Email</label>
                    <input type="email" id="register-email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="register-password" class="form-label">Mot de passe</label>
                    <input type="password" id="register-password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="register-tel" class="form-label">Téléphone</label>
                    <input type="tel" id="register-tel" name="tel" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="register-photo" class="form-label">Photo de profil</label>
                    <input type="file" id="register-photo" name="photo_profil" class="form-control" accept="image/*">
                </div>
                <button type="submit" name="register" class="btn btn-primary w-100 fw-bold">
                    <i class="fa-solid fa-user-plus"></i> S'inscrire
                </button>
                <div class="toggle-link text-center mt-3">
                    Déjà un compte ? <a href="login.php" class="text-primary text-decoration-underline">Connectez-vous</a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
   
</body>
</html>