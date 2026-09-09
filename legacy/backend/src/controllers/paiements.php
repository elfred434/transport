<?php
/**
 * Contrôleur Paiements : consultation d'un paiement (propriétaire),
 * paiement simulé (carte / mobile money), liste des paiements de l'utilisateur.
 */

function paiement_for_colis(array $params): void
{
    $user = require_auth();
    $colisId = (int) $params['colis_id'];
    $reference = query_str('reference');

    $sql = "SELECT p.*, c.nom_colis, c.prix_estime
            FROM paiements p
            JOIN colis c ON p.colis_id = c.id
            WHERE p.colis_id = ? AND p.user_id = ?";
    $sqlParams = [$colisId, (int) $user['id']];
    if ($reference !== '') {
        $sql .= " AND p.reference = ?";
        $sqlParams[] = $reference;
    }
    $sql .= " ORDER BY p.date_creation DESC LIMIT 1";

    $stmt = db()->prepare($sql);
    $stmt->execute($sqlParams);
    $paiement = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$paiement) {
        api_error('Paiement introuvable ou accès non autorisé', 404);
    }

    $paiement['details_paiement'] = $paiement['details_paiement']
        ? json_decode($paiement['details_paiement'], true)
        : null;

    json_success($paiement);
}

function paiements_mine(array $params): void
{
    $user = require_auth();

    $stmt = db()->prepare(
        "SELECT p.*, c.nom_colis
         FROM paiements p
         LEFT JOIN colis c ON p.colis_id = c.id
         WHERE p.user_id = ?
         ORDER BY p.date_creation DESC"
    );
    $stmt->execute([(int) $user['id']]);
    $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($paiements as &$p) {
        $p['details_paiement'] = $p['details_paiement'] ? json_decode($p['details_paiement'], true) : null;
    }
    unset($p);

    $stats = [
        'montant_total' => 0.0,
        'montant_paye' => 0.0,
        'montant_en_attente' => 0.0,
    ];
    foreach ($paiements as $p) {
        $stats['montant_total'] += (float) $p['montant'];
        if ($p['statut'] === 'paye') {
            $stats['montant_paye'] += (float) $p['montant'];
        } elseif ($p['statut'] === 'en_attente') {
            $stats['montant_en_attente'] += (float) $p['montant'];
        }
    }

    json_success(['paiements' => $paiements, 'stats' => $stats]);
}

function paiement_payer(array $params): void
{
    $user = require_auth();
    $paiementId = (int) $params['id'];

    $stmt = db()->prepare("SELECT * FROM paiements WHERE id = ? AND user_id = ?");
    $stmt->execute([$paiementId, (int) $user['id']]);
    $paiement = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$paiement) {
        api_error('Paiement introuvable ou accès non autorisé', 404);
    }
    if ($paiement['statut'] === 'paye') {
        api_error('Ce paiement a déjà été effectué');
    }

    $methode = input_str('methode_paiement');
    $operateur = input_str('operateur');
    $erreurs = [];

    $details = ['methode' => $methode, 'montant' => $paiement['montant']];

    if ($methode === 'carte_credit') {
        $numero_carte = preg_replace('/\s+/', '', input_str('numero_carte'));
        $expiration = input_str('expiration');
        $cvv = input_str('cvv');
        if (!preg_match('/^\d{12,19}$/', $numero_carte)) $erreurs[] = 'Numéro de carte invalide (12 à 19 chiffres)';
        if (!preg_match('/^(0[1-9]|1[0-2])\/?\d{2}$/', $expiration)) $erreurs[] = "Date d'expiration invalide (MM/AA)";
        if (!preg_match('/^\d{3,4}$/', $cvv)) $erreurs[] = 'Code CVV invalide';
        if (!$erreurs) {
            // Aucune donnée sensible stockée : uniquement un numéro masqué
            $details['numero_masque'] = substr($numero_carte, 0, 4) . str_repeat('*', max(0, strlen($numero_carte) - 8)) . substr($numero_carte, -4);
            $details['expiration'] = $expiration;
        }
    } elseif ($methode === 'mobile_money') {
        $operateurs_valides = ['mtn', 'moov', 'wave', 'orange'];
        if (!in_array($operateur, $operateurs_valides, true)) {
            $erreurs[] = 'Opérateur Mobile Money invalide';
        }
        $details['operateur'] = $operateur;
    } else {
        $erreurs[] = 'Méthode de paiement invalide';
    }

    if ($erreurs) {
        api_error(implode(' — ', $erreurs));
    }

    $numero_transaction = strtoupper(substr($methode, 0, 3)) . time() . random_int(100, 999);

    db()->prepare(
        "UPDATE paiements SET
            statut = 'paye',
            methode_paiement = ?,
            numero_transaction = ?,
            operateur = ?,
            details_paiement = ?,
            date_paiement = NOW(),
            ip_client = ?,
            device_info = ?
         WHERE id = ?"
    )->execute([
        $methode,
        $numero_transaction,
        $methode === 'mobile_money' ? $operateur : null,
        json_encode($details),
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        $paiementId,
    ]);

    json_success([
        'message' => 'Paiement effectué avec succès',
        'numero_transaction' => $numero_transaction,
        'montant' => (float) $paiement['montant'],
    ]);
}
