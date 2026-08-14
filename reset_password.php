<?php
session_start();
$host = 'localhost';
$db = 'transport_db';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    die('Erreur de connexion à la base de données');
}

$token = $_GET['token'] ?? '';
$error = $_GET['error'] ?? '';

// Vérifier la validité du token
$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user && $error !== 'invalid_token') {
    header('Location: login.html?error=invalid_token');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe - Agence de Transport de Colis</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="card shadow-lg border-0 rounded-4 p-4" style="max-width: 420px; width: 100%;">
            <h2 class="text-center text-primary mb-4"><i class="fa-solid fa-key"></i> Nouveau mot de passe</h2>
            
            <?php if ($error === 'invalid_token'): ?>
                <div class="alert alert-danger">Le lien de réinitialisation est invalide ou a expiré.</div>
                <a href="login.html" class="btn btn-primary w-100 fw-bold">
                    <i class="fa-solid fa-right-to-bracket"></i> Retour à la connexion
                </a>
            <?php else: ?>
                <form action="auth.php" method="POST" autocomplete="off">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="mb-3">
                        <label for="new-password" class="form-label">Nouveau mot de passe</label>
                        <input type="password" id="new-password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm-password" class="form-label">Confirmer le mot de passe</label>
                        <input type="password" id="confirm-password" class="form-control" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary w-100 fw-bold">
                        <i class="fa-solid fa-key"></i> Réinitialiser le mot de passe
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation côté client pour vérifier que les mots de passe correspondent
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('new-password').value;
            const confirm = document.getElementById('confirm-password').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas!');
            }
        });
    </script>
</body>
</html>