<?php
require_once __DIR__ . '/functions.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Vérification si l'utilisateur est un transporteur
if ($_SESSION['role'] !== 'transporteur') {
    $_SESSION['message'] = "Vous n'êtes pas un transporteur";
    header('Location: index.php');
    exit;
}

$transporteur_id = isset($_GET['id']) ? intval($_GET['id']) : 0;


$stmt = $pdo->prepare("SELECT u.*, t.* FROM users u 
    LEFT JOIN transporteurs t ON u.id = t.user_id 
    WHERE u.id = ? AND u.role = 'transporteur'");
$stmt->execute([$transporteur_id]);
$transporteur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transporteur) {
    die("Transporteur introuvable.");
}


$stmt = $pdo->prepare("SELECT * FROM voyages WHERE user_id = ? AND statut = 'approuve' ORDER BY date_depart DESC");
$stmt->execute([$transporteur_id]);
$voyages = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $pdo->prepare("SELECT c.* FROM colis c
    JOIN reservations r ON c.id = r.colis_id
    JOIN voyages v ON r.voyage_id = v.id
    WHERE v.user_id = ? AND r.statut = 'accepte'
    ORDER BY c.date_post DESC");
$stmt->execute([$transporteur_id]);
$colis_transportes = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $pdo->prepare("SELECT a.*, u.nom, u.prenom, u.photo_profil FROM avis a
    JOIN users u ON a.user_id = u.id
    WHERE a.transporteur_id = ? AND a.statut = 'approuve'
    ORDER BY a.date_avis DESC");
$stmt->execute([$transporteur_id]);
$avis = $stmt->fetchAll(PDO::FETCH_ASSOC);


$note_moyenne = 0;
if (count($avis) > 0) {
    $total = 0;
    foreach ($avis as $a) {
        $total += $a['note'];
    }
    $note_moyenne = round($total / count($avis), 1);
}


$peut_poster_avis = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $transporteur_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations r
        JOIN voyages v ON r.voyage_id = v.id
        WHERE v.user_id = ? AND r.statut = 'accepte' AND r.colis_id IN (
            SELECT id FROM colis WHERE user_id = ?
        )");
    $stmt->execute([$transporteur_id, $_SESSION['user_id']]);
    $peut_poster_avis = $stmt->fetchColumn() > 0;


    $stmt = $pdo->prepare("SELECT COUNT(*) FROM avis WHERE user_id = ? AND transporteur_id = ?");
    $stmt->execute([$_SESSION['user_id'], $transporteur_id]);
    $deja_avis = $stmt->fetchColumn() > 0;
    $peut_poster_avis = $peut_poster_avis && !$deja_avis;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Profil du transporteur - <?= htmlspecialchars($transporteur['prenom'] . ' ' . $transporteur['nom']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #34495e;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .profile-header {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .profile-img-container {
            width: 150px;
            height: 150px;
            margin: 0 auto 1rem;
        }

        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .initials {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color), #2c3e50);
            color: white;
            font-size: 3.5rem;
            font-weight: bold;
        }

        .rating {
            color: var(--warning-color);
            font-size: 1.25rem;
            margin: 0.5rem 0;
        }

        .card-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            border: none;
        }

        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }

        .voyage-card,
        .colis-card {
            transition: all 0.3s ease;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }

        .voyage-card:hover,
        .colis-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .avis-card {
            border-left: 4px solid var(--primary-color);
            margin-bottom: 1.5rem;
            background-color: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .avis-user {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .avis-user-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1rem;
        }

        .avis-user-initials {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-weight: bold;
        }

        .badge-statut {
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            font-weight: 500;
        }

        .btn-contact {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            transition: all 0.3s;
        }

        .btn-contact:hover {
            background-color: #2980b9;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 0.5rem;
        }

        .online {
            background-color: var(--success-color);
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
        }

        .offline {
            background-color: #95a5a6;
        }


        .rating-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            margin: 1rem 0;
        }

        .rating-input input {
            display: none;
        }

        .rating-input label {
            color: #ddd;
            font-size: 2rem;
            padding: 0 0.2rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .rating-input input:checked~label,
        .rating-input label:hover,
        .rating-input label:hover~label {
            color: var(--warning-color);
        }

        .rating-input input:checked+label {
            color: var(--warning-color);
            transform: scale(1.1);
        }

        .avis-form textarea {
            min-height: 120px;
            border-radius: 8px;
            padding: 1rem;
        }

        .avis-form .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        .text-muted {
            color: #6c757d !important;
        }

        @media (max-width: 768px) {
            .profile-img-container {
                width: 120px;
                height: 120px;
            }

            .initials {
                font-size: 2.5rem;
            }
        }
        @media (max-width: 641px){
            .m{
                display: none;
            }
            .content{
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <div class="m">
        <?php include 'menu.php'; ?>
    </div>
    <div class="content">
        <div class="container py-5">
            <div class="row">
                <div class="col-lg-4">

                    <div class="profile-header text-center">
                        <div class="profile-img-container">
                            <?php if ($transporteur['photo_profil']): ?>
                                <img src="<?= htmlspecialchars($transporteur['photo_profil']) ?>" class="profile-img" alt="Photo de profil">
                            <?php else: ?>
                                <div class="profile-img initials">
                                    <?= strtoupper(substr($transporteur['prenom'], 0, 1) . substr($transporteur['nom'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h2 class="mb-2"><?= htmlspecialchars($transporteur['prenom'] . ' ' . $transporteur['nom']) ?></h2>
                        <p class="text-muted mb-3">
                            <i class="fas fa-truck"></i> Transporteur professionnel
                            <span class="status-dot <?= (rand(0, 1) ? 'online' : 'offline') ?>"></span>
                        </p>

                        <div class="rating mb-3">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?= $i > $note_moyenne ? ($i - $note_moyenne < 0.5 ? '-half-alt' : '') : '' ?>"></i>
                            <?php endfor; ?>
                            <span class="ms-2"><?= number_format($note_moyenne, 1) ?>/5 (<?= count($avis) ?> avis)</span>
                        </div>

                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <a href="messagerie.php?destinataire_id=<?= $transporteur['id'] ?>" class="btn btn-contact">
                                <i class="fas fa-envelope me-2"></i> Contacter
                            </a>
                        </div>
                    </div>


                    <div class="card card-section">
                        <div class="card-header">
                            <i class="fas fa-info-circle me-2"></i> Informations
                        </div>
                        <div class="card-body text-center">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transporteurInfoModal">
                                Voir les informations du transporteur
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">

                    <div class="card card-section">
                        <div class="card-header">
                            <i class="fas fa-route me-2"></i> Voyages proposés
                        </div>
                        <div class="card-body">
                            <?php if (!empty($voyages)): ?>
                                <div class="row g-3">
                                    <?php foreach ($voyages as $voyage): ?>
                                        <div class="col-md-6">
                                            <div class="card voyage-card h-100">
                                                <div class="card-body">
                                                    <h5 class="card-title d-flex align-items-center">
                                                        <span class="badge bg-primary me-2"><?= htmlspecialchars($voyage['pays_depart']) ?></span>
                                                        <i class="fas fa-arrow-right text-muted mx-2"></i>
                                                        <span class="badge bg-success"><?= htmlspecialchars($voyage['pays_destination']) ?></span>
                                                    </h5>
                                                    <hr>
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <div>
                                                            <i class="fas fa-calendar-day text-muted me-2"></i>
                                                            <?= date('d/m/Y', strtotime($voyage['date_depart'])) ?>
                                                        </div>
                                                        <div>
                                                            <i class="fas fa-clock text-muted me-2"></i>
                                                            <?= htmlspecialchars($voyage['heure_depart']) ?>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <i class="fas fa-weight-hanging text-muted me-2"></i>
                                                            <?= htmlspecialchars($voyage['poids_max']) ?> kg max
                                                        </div>
                                                        <span class="badge bg-success badge-statut">
                                                            <?= htmlspecialchars($voyage['statut']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    <i class="fas fa-info-circle me-2"></i> Ce transporteur n'a pas encore proposé de voyages.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>


                    <div class="card card-section">
                        <div class="card-header">
                            <i class="fas fa-boxes me-2"></i> Colis transportés
                        </div>
                        <div class="card-body">
                            <?php if (!empty($colis_transportes)): ?>
                                <div class="row g-3">
                                    <?php foreach ($colis_transportes as $colis): ?>
                                        <div class="col-md-6">
                                            <div class="card colis-card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex">
                                                        <div class="flex-shrink-0">
                                                            <?php if ($colis['image_colis']): ?>
                                                                <img src="<?= htmlspecialchars($colis['image_colis']) ?>" class="rounded me-3" width="80" height="80" alt="Colis">
                                                            <?php else: ?>
                                                                <img src="assets/default-colis.png" class="rounded me-3" width="80" height="80" alt="Colis">
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h5 class="card-title mb-1"><?= htmlspecialchars($colis['nom_colis']) ?></h5>
                                                            <p class="card-text small text-muted mb-1">
                                                                <i class="fas fa-location-dot me-1"></i>
                                                                <?= htmlspecialchars($colis['ville']) ?>, <?= htmlspecialchars($colis['pays']) ?>
                                                            </p>
                                                            <p class="card-text small text-muted">
                                                                <i class="fas fa-weight-hanging me-1"></i>
                                                                <?= htmlspecialchars($colis['poids']) ?> kg
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    <i class="fas fa-info-circle me-2"></i> Ce transporteur n'a pas encore transporté de colis.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>


                    <?php if ($peut_poster_avis): ?>
                        <div class="card card-section">
                            <div class="card-header">
                                <i class="fas fa-edit me-2"></i> Donnez votre avis
                            </div>
                            <div class="card-body">
                                <form action="traitement-avis.php" method="POST" class="avis-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="transporteur_id" value="<?= $transporteur_id ?>">

                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Votre note :</label>
                                        <div class="rating-input">
                                            <input type="radio" id="star5" name="note" value="5" required />
                                            <label for="star5" title="Excellent"><i class="fas fa-star"></i></label>
                                            <input type="radio" id="star4" name="note" value="4" />
                                            <label for="star4" title="Très bien"><i class="fas fa-star"></i></label>
                                            <input type="radio" id="star3" name="note" value="3" />
                                            <label for="star3" title="Moyen"><i class="fas fa-star"></i></label>
                                            <input type="radio" id="star2" name="note" value="2" />
                                            <label for="star2" title="Passable"><i class="fas fa-star"></i></label>
                                            <input type="radio" id="star1" name="note" value="1" />
                                            <label for="star1" title="Mauvais"><i class="fas fa-star"></i></label>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="commentaire" class="form-label fw-bold">Votre commentaire :</label>
                                        <textarea class="form-control" id="commentaire" name="commentaire"
                                            placeholder="Décrivez votre expérience avec ce transporteur..." required></textarea>
                                    </div>

                                    <div class="mb-4">
                                        <label for="colis_id" class="form-label fw-bold">Colis concerné (optionnel) :</label>
                                        <select class="form-select" id="colis_id" name="colis_id">
                                            <option value="">-- Sélectionnez un colis --</option>
                                            <?php
                                            $stmt = $pdo->prepare("SELECT c.id, c.nom_colis FROM colis c
                                        JOIN reservations r ON c.id = r.colis_id
                                        JOIN voyages v ON r.voyage_id = v.id
                                        WHERE c.user_id = ? AND v.user_id = ? AND r.statut = 'accepte'");
                                            $stmt->execute([$_SESSION['user_id'], $transporteur_id]);
                                            $colis_options = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                            foreach ($colis_options as $colis): ?>
                                                <option value="<?= $colis['id'] ?>"><?= htmlspecialchars($colis['nom_colis']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-paper-plane me-2"></i> Publier votre avis
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>


                    <div class="card card-section">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-star me-2"></i> Avis des clients
                            </div>
                            <span class="badge bg-primary rounded-pill"><?= count($avis) ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($avis)): ?>
                                <?php foreach ($avis as $a): ?>
                                    <div class="avis-card">
                                        <div class="avis-user">
                                            <?php if ($a['photo_profil']): ?>
                                                <img src="<?= htmlspecialchars($a['photo_profil']) ?>" class="avis-user-img" alt="Photo de profil">
                                            <?php else: ?>
                                                <div class="avis-user-initials">
                                                    <?= strtoupper(substr($a['prenom'], 0, 1) . substr($a['nom'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-0"><?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?></h6>
                                                <small class="text-muted">
                                                    <?= date('d/m/Y à H:i', strtotime($a['date_avis'])) ?>
                                                </small>
                                            </div>
                                        </div>

                                        <div class="rating mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i > $a['note'] ? '-half-alt' : '' ?>"></i>
                                            <?php endfor; ?>
                                        </div>

                                        <p class="mb-0"><?= htmlspecialchars($a['commentaire']) ?></p>

                                        <?php if ($a['colis_id']): ?>
                                            <?php
                                            $stmt = $pdo->prepare("SELECT nom_colis FROM colis WHERE id = ?");
                                            $stmt->execute([$a['colis_id']]);
                                            $nom_colis = $stmt->fetchColumn();
                                            ?>
                                            <div class="mt-3">
                                                <span class="badge bg-light text-dark">
                                                    <i class="fas fa-box me-1"></i> <?= htmlspecialchars($nom_colis) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    <i class="fas fa-info-circle me-2"></i> Ce transporteur n'a pas encore reçu d'avis.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modale pour les informations du transporteur -->
    <div class="modal fade" id="transporteurInfoModal" tabindex="-1" aria-labelledby="transporteurInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="transporteurInfoModalLabel">Informations sur le transporteur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <?php if ($transporteur['photo_profil']): ?>
                                    <img src="<?= htmlspecialchars($transporteur['photo_profil']) ?>" class="img-fluid rounded-circle mb-3" alt="Photo de profil" style="width: 150px; height: 150px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="initials" style="width: 150px; height: 150px; margin: 0 auto;">
                                        <?= strtoupper(substr($transporteur['prenom'], 0, 1) . substr($transporteur['nom'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h5><?= htmlspecialchars($transporteur['prenom'] . ' ' . $transporteur['nom']) ?></h5>
                            <p class="text-muted">
                                <i class="fas fa-truck"></i> Transporteur professionnel
                                <span class="status-dot <?= (rand(0, 1) ? 'online' : 'offline') ?>"></span>
                            </p>

                            <div class="mb-3">
                                <strong>Téléphone :</strong> <?= htmlspecialchars($transporteur['telephone']) ?>
                            </div>
                            <div class="mb-3">
                                <strong>Email :</strong> <?= htmlspecialchars($transporteur['email']) ?>
                            </div>
                            <?php if ($transporteur['compagnie']): ?>
                                <div class="mb-3">
                                    <strong>Compagnie :</strong> <?= htmlspecialchars($transporteur['compagnie']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($transporteur['vehicule']): ?>
                                <div class="mb-3">
                                    <strong>Véhicule :</strong> <?= htmlspecialchars($transporteur['vehicule']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($transporteur['numero_permis']): ?>
                                <div class="mb-3">
                                    <strong>Numéro de permis :</strong> <?= htmlspecialchars($transporteur['numero_permis']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($transporteur['adresse']): ?>
                                <div class="mb-3">
                                    <strong>Adresse :</strong><br>
                                    <?= nl2br(htmlspecialchars($transporteur['adresse'])) ?><br>
                                    <?= htmlspecialchars($transporteur['ville']) ?>, <?= htmlspecialchars($transporteur['pays']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($transporteur['photo_vehicule']): ?>
                        <div class="mt-4 text-center">
                            <h6 class="text-muted mb-3"><i class="fas fa-car-side me-2"></i> Son véhicule</h6>
                            <img src="<?= htmlspecialchars($transporteur['photo_vehicule']) ?>" class="img-fluid rounded-3" alt="Véhicule">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.rating-input label').forEach(label => {
            label.addEventListener('click', (e) => {

                document.querySelectorAll('.rating-input input').forEach(input => {
                    input.checked = false;
                });


                const radio = e.target.previousElementSibling;
                if (radio) {
                    radio.checked = true;
                    let current = radio;
                    while ((current = current.previousElementSibling) !== null) {
                        if (current.tagName === 'INPUT') {
                            current.checked = true;
                        }
                    }
                }
            });

            label.addEventListener('mouseover', (e) => {
                const radio = e.target.previousElementSibling;
                if (radio) {
                    let current = radio;
                    while ((current = current.previousElementSibling) !== null) {
                        if (current.tagName === 'INPUT') {
                            current.nextElementSibling.style.color = '#ffc107';
                        }
                    }
                }
            });

            label.addEventListener('mouseout', (e) => {
                document.querySelectorAll('.rating-input label').forEach(l => {
                    if (!l.previousElementSibling.checked) {
                        l.style.color = '#ddd';
                    }
                });
            });
        });
    </script>
</body>

</html>