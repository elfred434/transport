<?php
require_once __DIR__ . '/functions.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Vérification des paramètres
if (!isset($_GET['colis_id']) || !isset($_GET['reference'])) {
    header('Location: dashboard.php');
    exit;
}

$colis_id = intval($_GET['colis_id']);
$reference = $_GET['reference'];

// Récupération des infos du paiement
$stmt = $pdo->prepare("SELECT p.*, c.nom_colis, c.prix_estime, u.nom, u.prenom 
                       FROM paiements p 
                       JOIN colis c ON p.colis_id = c.id 
                       JOIN users u ON p.user_id = u.id
                       WHERE p.colis_id = ? AND p.reference = ? AND p.user_id = ?");
$stmt->execute([$colis_id, $reference, $_SESSION['user_id']]);
$paiement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paiement) {
    die("Paiement introuvable ou vous n'avez pas l'autorisation d'y accéder.");
}

// Initialisation des variables
$errors = [];
$success = false;
$operateurs_mobile = [
    'mtn' => 'MTN Mobile Money',
    'moov' => 'Moov Money',
    'wave' => 'Wave',
    'orange' => 'Orange Money'
];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payer'])) {
    csrf_check();
    // Récupération et validation des données
    $methode_paiement = $_POST['methode_paiement'] ?? '';
    $numero_carte = preg_replace('/\s+/', '', $_POST['numero_carte'] ?? '');
    $expiration = $_POST['expiration'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    $operateur = $_POST['operateur'] ?? '';

    // Validation
    if (empty($methode_paiement)) {
        $errors[] = "Veuillez sélectionner une méthode de paiement";
    }

    if ($methode_paiement === 'carte_credit') {
        if (empty($numero_carte) || !preg_match('/^\d{12,19}$/', $numero_carte)) {
            $errors[] = "Numéro de carte invalide (12 à 19 chiffres requis)";
        }
        if (empty($expiration) || !preg_match('/^(0[1-9]|1[0-2])\/?([0-9]{2})$/', $expiration)) {
            $errors[] = "Date d'expiration invalide (format MM/AA requis)";
        }
        if (empty($cvv) || !preg_match('/^\d{3,4}$/', $cvv)) {
            $errors[] = "Code CVV invalide (3 ou 4 chiffres requis)";
        }
    } elseif ($methode_paiement === 'mobile_money' && empty($operateur)) {
        $errors[] = "Veuillez sélectionner un opérateur Mobile Money";
    }

    // Si pas d'erreurs, procéder au paiement
    if (empty($errors)) {
        $pdo->beginTransaction();

        try {
            // Génération numéro de transaction
            $numero_transaction = strtoupper(substr($methode_paiement, 0, 3)) . time() . rand(100, 999);

            // Préparation des détails sécurisés
            $details = [
                'methode' => $methode_paiement,
                'montant' => $paiement['montant']
            ];

            if ($methode_paiement === 'carte_credit') {
                $details['numero_masque'] = substr($numero_carte, 0, 4) . str_repeat('*', 12) . substr($numero_carte, -4);
                $details['expiration'] = $expiration;
            } elseif ($methode_paiement === 'mobile_money') {
                $details['operateur'] = $operateur;
            }

            // Mise à jour du paiement
            $stmt = $pdo->prepare("UPDATE paiements SET 
                statut = 'paye',
                methode_paiement = ?,
                numero_transaction = ?,
                operateur = ?,
                details_paiement = ?,
                date_paiement = NOW(),
                ip_client = ?,
                device_info = ?
                WHERE id = ?");

            $stmt->execute([
                $methode_paiement,
                $numero_transaction,
                $methode_paiement === 'mobile_money' ? $operateur : null,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'],
                $paiement['id']
            ]);

            // Mise à jour du statut du colis
            $stmt = $pdo->prepare("UPDATE colis SET statut = 'en_attente' WHERE id = ?");
            $stmt->execute([$colis_id]);

            $pdo->commit();
            $success = true;

            // Préparation des données pour l'affichage du reçu
            $paiement['numero_transaction'] = $numero_transaction;
            $paiement['methode_paiement'] = $methode_paiement;
            $paiement['operateur'] = $operateur;
            $paiement['date_paiement'] = date('Y-m-d H:i:s');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erreur lors du traitement du paiement: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement du colis - SPIISTMOVE</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-6.7.2-web/fontawesome-free-6.7.2-web/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #10b981;
            --danger-color: #dc3545;
        }

        body {
            background-color: #f8f9fa;
        }

        .payment-container {
            max-width: 600px;
        }

        .payment-card {
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .payment-header {
            background: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }

        .payment-method {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .payment-method:hover {
            border-color: var(--primary-color);
            background-color: #f8f9ff;
        }

        .payment-method.active {
            border-color: var(--primary-color);
            background-color: #f0f5ff;
        }

        .payment-method input[type="radio"] {
            display: none;
        }

        .payment-method label {
            cursor: pointer;
            width: 100%;
            padding: 15px;
            margin: 0;
        }

        .payment-icon {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.25);
        }

        .btn-pay {
            background: var(--primary-color);
            border: none;
            padding: 12px;
            font-weight: 600;
        }

        .btn-pay:hover {
            background: #1d4ed8;
        }

        .receipt {
            display: none;
        }
    </style>
</head>

<body>
    <!-- Menu de navigation -->
    <nav class="menu-horizontal">
        <!-- Votre menu existant ici -->
    </nav>

    <div class="container py-5">
        <div class="payment-container mx-auto">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Erreur</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Formulaire de paiement (masqué après succès) -->
            <div class="payment-card card mb-4 <?= $success ? 'd-none' : '' ?>" id="paymentForm">
                <div class="card-header payment-header">
                    <h4 class="mb-0"><i class="fas fa-credit-card"></i> Paiement sécurisé</h4>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h5 class="mb-3">Détails du colis</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Référence:</span>
                            <strong><?= htmlspecialchars($paiement['reference']) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Nom du colis:</span>
                            <strong><?= htmlspecialchars($paiement['nom_colis']) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Montant:</span>
                            <strong class="text-primary"><?= number_format($paiement['prix_estime'], 0, ',', ' ') ?> F CFA</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Client:</span>
                            <strong><?= htmlspecialchars($paiement['prenom'] . ' ' . $paiement['nom']) ?></strong>
                        </div>
                    </div>

                    <hr>

                    <form method="POST" id="paymentFormElement">
                        <?= csrf_field() ?>
                        <h5 class="mb-3">Méthode de paiement</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="payment-method rounded" id="cardMethod">
                                    <input type="radio" name="methode_paiement" id="carte_credit" value="carte_credit" required>
                                    <label for="carte_credit" class="d-flex align-items-center">
                                        <i class="fab fa-cc-visa payment-icon me-3"></i>
                                        <span>Carte de crédit</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="payment-method rounded" id="mobileMethod">
                                    <input type="radio" name="methode_paiement" id="mobile_money" value="mobile_money">
                                    <label for="mobile_money" class="d-flex align-items-center">
                                        <i class="fas fa-mobile-alt payment-icon me-3"></i>
                                        <span>Mobile Money</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Section Carte de crédit -->
                        <div class="mb-4" id="creditCardSection" style="display: none;">
                            <div class="mb-3">
                                <label for="numero_carte" class="form-label">Numéro de carte</label>
                                <input type="text" class="form-control" id="numero_carte" name="numero_carte" placeholder="1234 5678 9012 3456" maxlength="19">
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label for="expiration" class="form-label">Date d'expiration</label>
                                    <input type="text" class="form-control" id="expiration" name="expiration" placeholder="MM/AA" maxlength="5">
                                </div>
                                <div class="col-md-6">
                                    <label for="cvv" class="form-label">Code de sécurité</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="cvv" name="cvv" placeholder="CVV" maxlength="4">
                                        <span class="input-group-text"><i class="fas fa-question-circle" title="3 ou 4 chiffres au dos de votre carte"></i></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section Mobile Money -->
                        <div class="mb-4" id="mobileMoneySection" style="display: none;">
                            <div class="mb-3">
                                <label for="operateur" class="form-label">Opérateur</label>
                                <select class="form-select" id="operateur" name="operateur">
                                    <option value="">Sélectionnez un opérateur</option>
                                    <?php foreach ($operateurs_mobile as $value => $label): ?>
                                        <option value="<?= $value ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Vous recevrez une demande de paiement sur votre mobile
                            </div>
                        </div>

                        <button type="submit" name="payer" class="btn btn-primary btn-pay w-100 mt-2">
                            <i class="fas fa-lock me-2"></i> Payer <?= number_format($paiement['prix_estime'], 0, ',', ' ') ?> F CFA
                        </button>
                    </form>
                </div>
            </div>

            <!-- Reçu de paiement (affiché après succès) -->
            <?php if ($success): ?>
                <div class="payment-card card" id="paymentReceipt">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="fas fa-check-circle"></i> Paiement confirmé</h4>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                            <h3 class="mt-3">Paiement réussi !</h3>
                        </div>

                        <div class="receipt-details">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Référence paiement:</span>
                                <strong><?= htmlspecialchars($paiement['reference']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Transaction ID:</span>
                                <strong><?= htmlspecialchars($paiement['numero_transaction']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Méthode:</span>
                                <strong>
                                    <?= $paiement['methode_paiement'] === 'carte_credit' ? 'Carte de crédit' : ($paiement['methode_paiement'] === 'mobile_money' ? $operateurs_mobile[$paiement['operateur']] : '') ?>
                                </strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Montant:</span>
                                <strong class="text-success"><?= number_format($paiement['montant'], 0, ',', ' ') ?> F CFA</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Date:</span>
                                <strong><?= date('d/m/Y H:i', strtotime($paiement['date_paiement'])) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Statut:</span>
                                <strong class="text-success">Confirmé</strong>
                            </div>
                        </div>

                        <hr>

                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Merci pour le paiement de votre colis. Votre colis est maintenant en attente de traitement.
                        </div>

                        <div class="d-grid gap-2">
                            <a href="dashboard.php" class="btn btn-outline-primary">
                                <i class="fas fa-tachometer-alt"></i> Retour au tableau de bord
                            </a>
                            <button class="btn btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print"></i> Imprimer ce reçu
                            </button>
                            <button class="btn btn-outline-secondary" id="downloadPdf">
                                <i class="fas fa-file-pdf"></i> Télécharger le PDF
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>


    <script src="html2pdf.bundle.min.js"></script>
    <script>
        // Gestion de l'affichage des méthodes de paiement
        document.querySelectorAll('input[name="methode_paiement"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('creditCardSection').style.display = 'none';
                document.getElementById('mobileMoneySection').style.display = 'none';
                document.querySelectorAll('.payment-method').forEach(method => {
                    method.classList.remove('active');
                });

                if (this.checked) {
                    if (this.value === 'carte_credit') {
                        document.getElementById('creditCardSection').style.display = 'block';
                        document.getElementById('cardMethod').classList.add('active');
                    } else if (this.value === 'mobile_money') {
                        document.getElementById('mobileMoneySection').style.display = 'block';
                        document.getElementById('mobileMethod').classList.add('active');
                    }
                }
            });
        });

        // Formatage automatique du numéro de carte
        document.getElementById('numero_carte')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '');
            if (value.length > 0) {
                value = value.match(new RegExp('.{1,4}', 'g')).join(' ');
            }
            e.target.value = value;
        });

        // Formatage automatique de la date d'expiration
        document.getElementById('expiration')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });

        // Validation avant soumission
        document.getElementById('paymentFormElement')?.addEventListener('submit', function(e) {
            const method = document.querySelector('input[name="methode_paiement"]:checked')?.value;

            if (method === 'carte_credit') {
                const numeroCarte = document.getElementById('numero_carte').value.replace(/\s+/g, '');
                const expiration = document.getElementById('expiration').value;
                const cvv = document.getElementById('cvv').value;

                if (!/^\d{12,19}$/.test(numeroCarte)) {
                    alert('Veuillez entrer un numéro de carte valide (12 à 19 chiffres)');
                    e.preventDefault();
                    return false;
                }

                if (!/^(0[1-9]|1[0-2])\/?([0-9]{2})$/.test(expiration)) {
                    alert('Veuillez entrer une date d\'expiration valide (format MM/AA)');
                    e.preventDefault();
                    return false;
                }

                if (!/^\d{3,4}$/.test(cvv)) {
                    alert('Veuillez entrer un code CVV valide (3 ou 4 chiffres)');
                    e.preventDefault();
                    return false;
                }
            } else if (method === 'mobile_money') {
                const operateur = document.getElementById('operateur').value;
                if (!operateur) {
                    alert('Veuillez sélectionner un opérateur Mobile Money');
                    e.preventDefault();
                    return false;
                }
            }
        });
        // Génération du PDF
        document.getElementById('downloadPdf')?.addEventListener('click', function() {
            const element = document.getElementById('paymentReceipt');
            const opt = {
                margin: 10,
                filename: 'recu_paiement_' + <?= json_encode($paiement['reference']) ?> + '.pdf',
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                }
            };

            // Générer le PDF
            html2pdf().from(element).set(opt).save();
        }); // Génération du PDF
        document.getElementById('downloadPdf')?.addEventListener('click', function() {
            const element = document.getElementById('paymentReceipt');
            const opt = {
                margin: 10,
                filename: 'recu_paiement_' + <?= json_encode($paiement['reference']) ?> + '.pdf',
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                }
            };

            // Générer le PDF
            html2pdf().from(element).set(opt).save();
        });
    </script>
</body>

</html>