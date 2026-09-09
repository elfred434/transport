<?php

require_once __DIR__ . '/functions.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$colis_id = isset($_GET['id']) ? intval($_GET['id']) : 0;


// Le colis doit appartenir à l'utilisateur connecté (ou être admin)
$stmt = $pdo->prepare("SELECT * FROM colis WHERE id = ?");
$stmt->execute([$colis_id]);
$colis = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$colis) {
    die("Colis introuvable.");
}
if (!is_admin() && (int) $colis['user_id'] !== (int) $_SESSION['user_id']) {
    http_response_code(403);
    die("Vous n'êtes pas autorisé à consulter les réservations de ce colis.");
}


$stmt = $pdo->prepare("SELECT r.*, v.pays_depart, v.pays_destination, v.date_depart, v.heure_depart, u.nom, u.prenom, u.email, u.telephone
    FROM reservations r
    JOIN voyages v ON r.voyage_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE r.colis_id = ?
    ORDER BY r.date_reservation DESC");
$stmt->execute([$colis_id]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'], $_POST['action'])) {
    csrf_check();
    $reservation_id = intval($_POST['reservation_id']);
    $action = $_POST['action'];
    
    // Vérifier que l'action est valide
    $actions_valides = ['accepte', 'refuse', 'annule'];
    if (!in_array($action, $actions_valides, true)) {
        die("Action non valide");
    }

    // La réservation doit concerner CE colis
    $stmt = $pdo->prepare("SELECT id FROM reservations WHERE id = ? AND colis_id = ?");
    $stmt->execute([$reservation_id, $colis_id]);
    if (!$stmt->fetch()) {
        die("Réservation introuvable pour ce colis.");
    }
    
    // Si c'est une annulation, on remet le statut à 'en_attente'
    $nouveau_statut = ($action === 'annule') ? 'en_attente' : $action;
    
    $stmt = $pdo->prepare("UPDATE reservations SET statut = ? WHERE id = ?");
    $stmt->execute([$nouveau_statut, $reservation_id]);
    
    // Si on annule, on peut aussi vouloir supprimer le suivi du colis
    if ($action === 'annule') {
        $stmt = $pdo->prepare("DELETE FROM suivi_colis WHERE colis_id = ?");
        $stmt->execute([$colis_id]);
    }
    
    header("Location: reservation-colis.php?id=$colis_id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Réservations pour le colis</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">

    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
</head>

<body class="bg-light">
    
    <div class="content">
        <div class="container py-5">
            <h2 class="text-primary text-center mb-4">
                <i class="fa-solid fa-box"></i> Réservations pour le colis : <?= htmlspecialchars($colis['nom_colis']) ?>
            </h2>
            <div class="mb-4">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle shadow-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Transporteur</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Départ</th>
                            <th>Destination</th>
                            <th>Date/Heure</th>
                            <th>Date réservation</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['prenom'] . ' ' . $r['nom']) ?></td>
                                <td><?= htmlspecialchars($r['email']) ?></td>
                                <td><?= htmlspecialchars($r['telephone']) ?></td>
                                <td><?= htmlspecialchars($r['pays_depart']) ?></td>
                                <td><?= htmlspecialchars($r['pays_destination']) ?></td>
                                <td><?= htmlspecialchars($r['date_depart']) ?> <?= htmlspecialchars($r['heure_depart']) ?></td>
                                <td><?= htmlspecialchars($r['date_reservation']) ?></td>
                                <td>
                                    <?php
                                    if ($r['statut'] == 'en_attente') echo '<span class="badge bg-warning text-dark">En attente</span>';
                                    elseif ($r['statut'] == 'accepte') echo '<span class="badge bg-success">Accepté</span>';
                                    elseif ($r['statut'] == 'refuse') echo '<span class="badge bg-danger">Refusé</span>';
                                    elseif ($r['statut'] == 'termine') echo '<span class="badge bg-secondary">Terminé</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($r['statut'] == 'en_attente'): ?>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
                                            <button type="submit" name="action" value="accepte" class="btn btn-success btn-sm">
                                                <i class="fa-solid fa-check"></i> Accepter
                                            </button>
                                            <button type="submit" name="action" value="refuse" class="btn btn-danger btn-sm">
                                                <i class="fa-solid fa-xmark"></i> Refuser
                                            </button>
                                        </form>
                                    <?php elseif ($r['statut'] == 'accepte'): ?>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
                                            <button type="submit" name="action" value="annule" class="btn btn-warning btn-sm">
                                                <i class="fa-solid fa-rotate-left"></i> Annuler
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reservations)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">Aucune réservation pour ce colis.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Confirmation avant annulation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (this.querySelector('[name="action"][value="annule"]')) {
                if (!confirm('Êtes-vous sûr de vouloir annuler cette réservation ? Le transporteur sera notifié.')) {
                    e.preventDefault();
                }
            }
        });
    });
});
</script>
</body>

</html>