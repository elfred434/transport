<?php
session_start();

// Vérifiez si le lien existe dans la session
$reset_link = $_SESSION['reset_link'] ?? 'Lien non disponible';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lien envoyé - Agence de Transport de Colis</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="card shadow-lg border-0 rounded-4 p-4 text

-center" style="max-width: 420px; width: 100%;">
            <h2 class="text-primary mb-4"><i class="fa-solid fa-envelope-circle-check"></i> Lien envoyé</h2>
            <p class="mb-4">Un email avec un lien de réinitialisation a été envoyé à votre adresse email.</p>
            <?php if ($reset_link !== 'Lien non disponible'): ?>
                <p class="small text-muted mb-4">Pour les besoins de test, voici le lien généré :</p>
                <div class="alert alert-info small">
                    <?php echo htmlspecialchars($reset_link); ?>
                </div>
            <?php else: ?>
                <p class="small text-danger mb-4">Le lien de réinitialisation n'est pas disponible. Veuillez réessayer.</p>
            <?php endif; ?>
            <a href="login.html" class="btn btn-primary w-100 fw-bold" onclick="<?php unset($_SESSION['reset_link']); ?>">
                <i class="fa-solid fa-right-to-bracket"></i> Retour à la connexion
            </a>
        </div>
    </div>
    <script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>