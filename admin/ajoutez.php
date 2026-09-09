<?php
require_once __DIR__ . '/../functions.php';

// Générer un jeton CSRF si non défini
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Connexion à la base de données

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Erreur de validation CSRF.";
    } else {
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $role = $_POST['role'];

        // Validation des champs
        if (empty($nom) || empty($prenom) || empty($email) || empty($password) || empty($role)) {
            $error = "Veuillez remplir tous les champs.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "L'email n'est pas valide.";
        } elseif (strlen($password) < 8) {
            $error = "Le mot de passe doit contenir au moins 8 caractères.";
        } elseif (!in_array($role, ['utilisateur', 'transporteur', 'admin'])) {
            $error = "Rôle invalide.";
        } else {
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Cet email est déjà utilisé.";
            } else {
                // Hacher le mot de passe et insérer l'utilisateur
                $password_hashed = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, email, password, role, date_inscription) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$nom, $prenom, $email, $password_hashed, $role]);
                    $success = "Administrateur créé avec succès.";
                    // Régénérer le jeton CSRF après soumission réussie
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } catch (Exception $e) {
                    error_log("Erreur création utilisateur : " . $e->getMessage());
                    $error = "Erreur lors de la création de l'utilisateur.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un utilisateur</title>
    <link rel="stylesheet" href="../bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --primary-color: #1a237e;
            --light-color: #f4f8fb;
        }
        body {
            background: var(--light-color);
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: var(--sidebar-width);
            background: var(--primary-color);
            color: white;
            position: fixed;
            height: 100vh;
            padding: 20px 0;
            transition: all 0.3s;
        }
        .sidebar-brand {
            padding: 10px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar-brand img {
            height: 40px;
            margin-right: 10px;
        }
        .sidebar-nav {
            list-style: none;
            padding: 0;
        }
        .sidebar-nav li {
            margin-bottom: 5px;
        }
        .sidebar-nav a {
            color: white;
            padding: 10px 20px;
            display: block;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: rgba(255,255,255,0.1);
            border-left: 3px solid white;
        }
        .sidebar-nav i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 20px;
        }
        .form-container {
            max-width: 600px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            .sidebar-brand span, .sidebar-nav a span {
                display: none;
            }
            .sidebar-nav i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            .main-content {
                margin-left: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-truck fa-2x"></i>
            <span>Admin Transport</span>
        </div>
        <ul class="sidebar-nav">
            <li><a href="admin.php?section=dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span>Tableau de bord</span>
            </a></li>
            <li><a href="admin.php?section=utilisateurs" class="active">
                <i class="fas fa-users"></i>
                <span>Utilisateurs</span>
            </a></li>
            <li><a href="admin.php?section=colis">
                <i class="fas fa-box"></i>
                <span>Colis</span>
            </a></li>
            <li><a href="admin.php?section=voyages">
                <i class="fas fa-route"></i>
                <span>Voyages</span>
            </a></li>
            <li><a href="admin.php?section=transporteurs">
                <i class="fas fa-truck-moving"></i>
                <span>Transporteurs</span>
            </a></li>
            <li><a href="admin.php?section=paiements">
                <i class="fas fa-money-bill-wave"></i>
                <span>Paiements</span>
            </a></li>
            <li><a href="admin.php?section=avis">
                <i class="fas fa-star"></i>
                <span>Avis</span>
            </a></li>
            <li><a href="admin.php?section=messages">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
            </a></li>
            <li><a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a></li>
        </ul>
    </div>

    <div class="main-content">
        <h2 class="mb-4 text-center text-primary fw-bold">Ajouter un utilisateur</h2>
        <div class="form-container mx-auto">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="mb-3">
                    <label for="nom" class="form-label">Nom</label>
                    <input type="text" class="form-control" id="nom" name="nom" required value="<?= isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : '' ?>">
                </div>
                <div class="mb-3">
                    <label for="prenom" class="form-label">Prénom</label>
                    <input type="text" class="form-control" id="prenom" name="prenom" required value="<?= isset($_POST['prenom']) ? htmlspecialchars($_POST['prenom']) : '' ?>">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Mot de passe</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="role" class="form-label">Rôle</label>
                    <select class="form-control" id="role" name="role" required>
                        <option value="admin" <?= isset($_POST['role']) && $_POST['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                        <option value="transporteur" <?= isset($_POST['role']) && $_POST['role'] === 'transporteur' ? 'selected' : '' ?>>Transporteur</option>
                        <option value="utilisateur" <?= isset($_POST['role']) && $_POST['role'] === 'utilisateur' ? 'selected' : '' ?>>Utilisateur</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100">Ajouter l'utilisateur</button>
            </form>
        </div>
    </div>
    <script src="../bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>