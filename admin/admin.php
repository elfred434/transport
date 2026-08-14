<?php
session_start();

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

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

// Paramètres communs pour toutes les sections
$items_per_page = 10; // Nombre d'éléments par page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';

// Récupération des statistiques pour le dashboard
$nb_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$nb_colis = $pdo->query("SELECT COUNT(*) FROM colis")->fetchColumn();
$nb_voyages = $pdo->query("SELECT COUNT(*) FROM voyages")->fetchColumn();
$nb_reservations = $pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
$nb_transporteurs = $pdo->query("SELECT COUNT(*) FROM transporteurs")->fetchColumn();
$nb_avis = $pdo->query("SELECT COUNT(*) FROM avis")->fetchColumn();
$nb_paiements = $pdo->query("SELECT COUNT(*) FROM paiements")->fetchColumn();
$total_paiements = $pdo->query("SELECT SUM(montant) FROM paiements WHERE statut = 'complete'")->fetchColumn();

// Section Colis
if ($section === 'colis') {
    // Filtres
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statut = isset($_GET['statut']) ? $_GET['statut'] : '';
    $type_produit = isset($_GET['type_produit']) ? $_GET['type_produit'] : '';
    $date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
    $date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

    // Construction de la requête
    $query = "SELECT c.*, u.nom, u.prenom, 
             (SELECT statut FROM suivi_colis WHERE colis_id = c.id AND (confirme_par_admin = TRUE OR statut != 'Livré') ORDER BY date_etape DESC LIMIT 1) as statut_livraison,
             (SELECT id FROM suivi_colis WHERE colis_id = c.id AND demande_livraison = TRUE AND confirme_par_admin = FALSE ORDER BY date_etape DESC LIMIT 1) as demande_livraison_id
             FROM colis c 
             LEFT JOIN users u ON c.user_id = u.id 
             WHERE 1=1";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (c.nom_colis LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($statut)) {
        $query .= " AND c.statut = ?";
        $params[] = $statut;
    }
    
    if (!empty($type_produit)) {
        $query .= " AND c.type_produit = ?";
        $params[] = $type_produit;
    }
    
    if (!empty($date_debut) && !empty($date_fin)) {
        $query .= " AND c.date_post BETWEEN ? AND ?";
        $params[] = $date_debut;
        $params[] = $date_fin;
    }
    
    $query .= " ORDER BY c.date_post DESC LIMIT $items_per_page OFFSET $offset";
    
    // Exécution de la requête
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $colis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Requête pour le nombre total d'éléments
    $count_query = "SELECT COUNT(*) FROM colis c LEFT JOIN users u ON c.user_id = u.id WHERE 1=1";
    
    if (!empty($search)) {
        $count_query .= " AND (c.nom_colis LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)";
    }
    
    if (!empty($statut)) {
        $count_query .= " AND c.statut = ?";
    }
    
    if (!empty($type_produit)) {
        $count_query .= " AND c.type_produit = ?";
    }
    
    if (!empty($date_debut) && !empty($date_fin)) {
        $count_query .= " AND c.date_post BETWEEN ? AND ?";
    }
    
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_items = $stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);


    if (isset($_GET['approuve'])) {
        $id = intval($_GET['approuve']);
        $pdo->prepare("UPDATE colis SET statut = 'approuve' WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Colis approuvé avec succès";
        header('Location: admin.php?section=colis');
        exit;
    }

    if (isset($_GET['refuse'])) {
        $id = intval($_GET['refuse']);
        $pdo->prepare("UPDATE colis SET statut = 'refuse' WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Colis refusé avec succès";
        header('Location: admin.php?section=colis');
        exit;
    }

    if (isset($_GET['reset_status'])) {
        $id = intval($_GET['reset_status']);
        $pdo->prepare("UPDATE colis SET statut = 'en_attente' WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Statut du colis réinitialisé avec succès";
        header('Location: admin.php?section=colis');
        exit;
    }

    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);

        $stmt = $pdo->prepare("SELECT statut FROM suivi_colis WHERE colis_id = ? ORDER BY date_etape DESC LIMIT 1");
        $stmt->execute([$id]);
        $statut_livraison = $stmt->fetchColumn();

        if ($statut_livraison === 'En cours') {
            $_SESSION['error'] = "Impossible de supprimer un colis en cours de livraison";
            header('Location: admin.php?section=colis');
            exit;
        }

        $pdo->prepare("DELETE FROM colis WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Colis supprimé avec succès";
        header('Location: admin.php?section=colis');
        exit;
    }

    if (isset($_GET['confirmer_livraison'])) {
        $id = intval($_GET['confirmer_livraison']);

        // Récupérer les informations du colis et du transporteur
        $stmt = $pdo->prepare("
            SELECT sc.colis_id, c.prix_estime, r.voyage_id, v.user_id AS transporteur_id
            FROM suivi_colis sc
            JOIN colis c ON sc.colis_id = c.id
            JOIN reservations r ON c.id = r.colis_id
            JOIN voyages v ON r.voyage_id = v.id
            WHERE sc.id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $colis_id = $data['colis_id'];
            $prix_estime = $data['prix_estime'];
            $transporteur_id = $data['transporteur_id'];

            // Calculer 5 % du montant payé
            $commission = $prix_estime * 0.05;

            // Ajouter la commission au solde du transporteur
            $stmt = $pdo->prepare("UPDATE transporteurs SET solde = solde + ? WHERE user_id = ?");
            $stmt->execute([$commission, $transporteur_id]);

            // Confirmer la livraison
            $stmt = $pdo->prepare("UPDATE suivi_colis SET confirme_par_admin = TRUE WHERE id = ?");
            $stmt->execute([$id]);

            $_SESSION['success'] = "Livraison confirmée avec succès et 5 % attribués au transporteur.";
        } else {
            $_SESSION['error'] = "Erreur : Informations du colis introuvables.";
        }

        header('Location: admin.php?section=colis');
        exit;
    }

    if (isset($_GET['refuser_livraison'])) {
        $id = intval($_GET['refuser_livraison']);
        $pdo->prepare("DELETE FROM suivi_colis WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Demande de livraison refusée";
        header('Location: admin.php?section=colis');
        exit;
    }

    $colis = $pdo->query(
        "SELECT c.*, u.nom, u.prenom, 
        (SELECT statut FROM suivi_colis WHERE colis_id = c.id AND (confirme_par_admin = TRUE OR statut != 'Livré') ORDER BY date_etape DESC LIMIT 1) as statut_livraison,
        (SELECT id FROM suivi_colis WHERE colis_id = c.id AND demande_livraison = TRUE AND confirme_par_admin = FALSE ORDER BY date_etape DESC LIMIT 1) as demande_livraison_id
        FROM colis c 
        LEFT JOIN users u ON c.user_id = u.id 
        ORDER BY c.date_post DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
}
// Section Utilisateurs
elseif ($section === 'utilisateurs') {
    // Filtres
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $role = isset($_GET['role']) ? $_GET['role'] : '';
    $date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
    $date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

    // Construction de la requête
    $query = "SELECT * FROM users WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR telephone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($role)) {
        $query .= " AND role = ?";
        $params[] = $role;
    }
    
    if (!empty($date_debut) && !empty($date_fin)) {
        $query .= " AND date_inscription BETWEEN ? AND ?";
        $params[] = $date_debut;
        $params[] = $date_fin;
    }
    
    $query .= " ORDER BY date_inscription DESC LIMIT $items_per_page OFFSET $offset";
    
    // Exécution de la requête
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Requête pour le nombre total d'éléments
    $count_query = "SELECT COUNT(*) FROM users WHERE 1=1";
    
    if (!empty($search)) {
        $count_query .= " AND (nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR telephone LIKE ?)";
    }
    
    if (!empty($role)) {
        $count_query .= " AND role = ?";
    }
    
    if (!empty($date_debut) && !empty($date_fin)) {
        $count_query .= " AND date_inscription BETWEEN ? AND ?";
    }
    
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_items = $stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Utilisateur supprimé avec succès";
        header('Location: admin.php?section=utilisateurs');
        exit;
    }
    $users = $pdo->query("SELECT * FROM users ORDER BY date_inscription DESC")->fetchAll(PDO::FETCH_ASSOC);
}
// Section Voyages
elseif ($section === 'voyages') {
    // Filtres
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $pays_depart = isset($_GET['pays_depart']) ? $_GET['pays_depart'] : '';
    $pays_destination = isset($_GET['pays_destination']) ? $_GET['pays_destination'] : '';
    $statut = isset($_GET['statut']) ? $_GET['statut'] : '';
    $date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
    $date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

    // Construction de la requête
    $query = "SELECT v.*, u.nom, u.prenom FROM voyages v LEFT JOIN users u ON v.user_id = u.id WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR v.email LIKE ? OR v.telephone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($pays_depart)) {
        $query .= " AND v.pays_depart = ?";
        $params[] = $pays_depart;
    }
    
    if (!empty($pays_destination)) {
        $query .= " AND v.pays_destination = ?";
        $params[] = $pays_destination;
    }
    
    if (!empty($statut)) {
        $query .= " AND v.statut = ?";
        $params[] = $statut;
    }
    
    if (!empty($date_debut) && !empty($date_fin)) {
        $query .= " AND v.date_post BETWEEN ? AND ?";
        $params[] = $date_debut;
        $params[] = $date_fin;
    }
    
    $query .= " ORDER BY v.date_post DESC LIMIT $items_per_page OFFSET $offset";
    
    // Exécution de la requête
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $voyages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Requête pour le nombre total d'éléments
    $count_query = "SELECT COUNT(*) FROM voyages v LEFT JOIN users u ON v.user_id = u.id WHERE 1=1";
    
    if (!empty($search)) {
        $count_query .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR v.email LIKE ? OR v.telephone LIKE ?)";
    }
    
    if (!empty($pays_depart)) {
        $count_query .= " AND v.pays_depart = ?";
    }
    
    if (!empty($pays_destination)) {
        $count_query .= " AND v.pays_destination = ?";
    }
    
    if (!empty($statut)) {
        $count_query .= " AND v.statut = ?";
    }
    
    if (!empty($date_debut) && !empty($date_fin)) {
        $count_query .= " AND v.date_post BETWEEN ? AND ?";
    }
    
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_items = $stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    if (isset($_GET['approuve'])) {
        $id = intval($_GET['approuve']);
        $pdo->prepare("UPDATE voyages SET statut = 'approuve' WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Voyage approuvé avec succès";
        header('Location: admin.php?section=voyages');
        exit;
    }
    if (isset($_GET['refuse'])) {
        $id = intval($_GET['refuse']);
        $pdo->prepare("UPDATE voyages SET statut = 'refuse' WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Voyage refusé avec succès";
        header('Location: admin.php?section=voyages');
        exit;
    }
    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        $pdo->prepare("DELETE FROM voyages WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Voyage supprimé avec succès";
        header('Location: admin.php?section=voyages');
        exit;
    }
    $voyages = $pdo->query("SELECT v.*, u.nom, u.prenom FROM voyages v LEFT JOIN users u ON v.user_id = u.id ORDER BY v.date_post DESC")->fetchAll(PDO::FETCH_ASSOC);
}
// Section Transporteurs
elseif ($section === 'transporteurs') {
    // Pagination
    $items_per_page = 10; // Nombre d'éléments par page
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $items_per_page;

    // Filtres
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $country = isset($_GET['country']) ? trim($_GET['country']) : '';

    // Construire la requête avec les filtres
    $query = "
        SELECT t.*, u.nom, u.prenom, u.email, u.telephone 
        FROM transporteurs t 
        LEFT JOIN users u ON t.user_id = u.id 
        WHERE 1=1
    ";

    $params = [];
    if (!empty($search)) {
        $query .= " AND (u.nom LIKE ? OR u.prenom LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if (!empty($country)) {
        $query .= " AND t.pays = ?";
        $params[] = $country;
    }

    $query .= " ORDER BY t.date_creation DESC LIMIT $items_per_page OFFSET $offset";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transporteurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compter le nombre total d'éléments pour la pagination
    $count_query = "
        SELECT COUNT(*) 
        FROM transporteurs t 
        LEFT JOIN users u ON t.user_id = u.id 
        WHERE 1=1
    ";
    if (!empty($search)) {
        $count_query .= " AND (u.nom LIKE ? OR u.prenom LIKE ?)";
    }
    if (!empty($country)) {
        $count_query .= " AND t.pays = ?";
    }

    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_items = $stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);
}
// Section Paiements
elseif ($section === 'paiements') {
    // Filtres
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statut = isset($_GET['statut']) ? $_GET['statut'] : '';
    $methode = isset($_GET['methode']) ? $_GET['methode'] : '';
    $date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
    $date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

    // Construction de la requête
    $query = "SELECT p.*, 
             u.nom as user_nom, u.prenom as user_prenom, u.email as user_email,
             c.nom_colis, c.prix_estime
             FROM paiements p
             LEFT JOIN users u ON p.user_id = u.id
             LEFT JOIN colis c ON p.colis_id = c.id
             WHERE 1=1";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR p.reference LIKE ? OR p.numero_transaction LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($statut)) {
        $query .= " AND p.statut = ?";
        $params[] = $statut;
    }
    
    if (!empty($methode)) {
        $query .= " AND p.methode_paiement = ?";
        $params[] = $methode;
    }
    
    if (!empty($date_debut) && !empty($date_fin)) {
        $query .= " AND p.date_creation BETWEEN ? AND ?";
        $params[] = $date_debut;
        $params[] = $date_fin;
    }
    
    $query .= " ORDER BY p.date_creation DESC LIMIT $items_per_page OFFSET $offset";
    
    // Exécution de la requête
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Requête pour le nombre total d'éléments
    $count_query = "SELECT COUNT(*) FROM paiements p LEFT JOIN users u ON p.user_id = u.id LEFT JOIN colis c ON p.colis_id = c.id WHERE 1=1";
    
    if (!empty($search)) {
        $count_query .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR p.reference LIKE ? OR p.numero_transaction LIKE ?)";
    }
    
    if (!empty($statut)) {
        $count_query .= " AND p.statut = ?";
    }
    
    if (!empty($methode)) {
        $count_query .= " AND p.methode_paiement = ?";
    }
    
    if (!empty($date_debut) && !empty($date_fin)) {
        $count_query .= " AND p.date_creation BETWEEN ? AND ?";
    }
    
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_items = $stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    if (isset($_GET['confirmer'])) {
        $id = intval($_GET['confirmer']);
        $pdo->prepare("UPDATE paiements SET statut = 'paye', date_paiement = NOW() WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Paiement confirmé avec succès";
        header('Location: admin.php?section=paiements');
        exit;
    }

    if (isset($_GET['annuler'])) {
        $id = intval($_GET['annuler']);
        $pdo->prepare("UPDATE paiements SET statut = 'annule' WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Paiement annulé avec succès";
        header('Location: admin.php?section=paiements');
        exit;
    }

    if (isset($_GET['marquer_echec'])) {
        $id = intval($_GET['marquer_echec']);
        $pdo->prepare("UPDATE paiements SET statut = 'echec' WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Paiement marqué comme échoué";
        header('Location: admin.php?section=paiements');
        exit;
    }

    $paiements = $pdo->query(
        "SELECT p.*, 
         u.nom as user_nom, u.prenom as user_prenom, u.email as user_email,
         c.nom_colis, c.prix_estime
         FROM paiements p
         LEFT JOIN users u ON p.user_id = u.id
         LEFT JOIN colis c ON p.colis_id = c.id
         ORDER BY p.date_creation DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques pour le dashboard
    $stats_paiements = $pdo->query(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
            SUM(CASE WHEN statut = 'paye' THEN 1 ELSE 0 END) as payes,
            SUM(CASE WHEN statut = 'echec' THEN 1 ELSE 0 END) as echecs,
            SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) as annules,
            SUM(CASE WHEN statut = 'paye' THEN montant ELSE 0 END) as montant_total
         FROM paiements"
    )->fetch(PDO::FETCH_ASSOC);
}
// Section Avis
elseif ($section === 'avis') {
    // Filtres
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $note = isset($_GET['note']) ? $_GET['note'] : '';
    $statut = isset($_GET['statut']) ? $_GET['statut'] : '';
    $date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
    $date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

    // Construction de la requête
    $query = "SELECT a.*, 
             u.nom as user_nom, u.prenom as user_prenom,
             t.nom as transporteur_nom, t.prenom as transporteur_prenom,
             c.nom_colis
             FROM avis a
             LEFT JOIN users u ON a.user_id = u.id
             LEFT JOIN users t ON a.transporteur_id = t.id
             LEFT JOIN colis c ON a.colis_id = c.id
             WHERE 1=1";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR t.nom LIKE ? OR t.prenom LIKE ? OR c.nom_colis LIKE ? OR a.commentaire LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($note)) {
        $query .= " AND a.note = ?";
        $params[] = $note;
    }
    
    if (!empty($statut)) {
        $query .= " AND a.statut = ?";
        $params[] = $statut;
    }
    
    if (!empty($date_debut) && !empty($date_fin)) {
        $query .= " AND a.date_avis BETWEEN ? AND ?";
        $params[] = $date_debut;
        $params[] = $date_fin;
    }
    
    $query .= " ORDER BY a.date_avis DESC LIMIT $items_per_page OFFSET $offset";
    
    // Exécution de la requête
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Requête pour le nombre total d'éléments
    $count_query = "SELECT COUNT(*) FROM avis a 
                   LEFT JOIN users u ON a.user_id = u.id
                   LEFT JOIN users t ON a.transporteur_id = t.id
                   LEFT JOIN colis c ON a.colis_id = c.id
                   WHERE 1=1";
    
    if (!empty($search)) {
        $count_query .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR t.nom LIKE ? OR t.prenom LIKE ? OR c.nom_colis LIKE ? OR a.commentaire LIKE ?)";
    }
    
    if (!empty($note)) {
        $count_query .= " AND a.note = ?";
    }
    
    if (!empty($statut)) {
        $count_query .= " AND a.statut = ?";
    }
    
    if (!empty($date_debut) && !empty($date_fin)) {
        $count_query .= " AND a.date_avis BETWEEN ? AND ?";
    }
    
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_items = $stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);
    if (isset($_GET['approuve'])) {
        $id = intval($_GET['approuve']);
        $pdo->prepare("UPDATE avis SET statut = 'approuve' WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Avis approuvé avec succès";
        header('Location: admin.php?section=avis');
        exit;
    }

    if (isset($_GET['refuse'])) {
        $id = intval($_GET['refuse']);
        $pdo->prepare("UPDATE avis SET statut = 'refuse' WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Avis refusé avec succès";
        header('Location: admin.php?section=avis');
        exit;
    }

    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        $pdo->prepare("DELETE FROM avis WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Avis supprimé avec succès";
        header('Location: admin.php?section=avis');
        exit;
    }

    $avis = $pdo->query(
        "SELECT a.*, 
         u.nom as user_nom, u.prenom as user_prenom,
         t.nom as transporteur_nom, t.prenom as transporteur_prenom,
         c.nom_colis
         FROM avis a
         LEFT JOIN users u ON a.user_id = u.id
         LEFT JOIN users t ON a.transporteur_id = t.id
         LEFT JOIN colis c ON a.colis_id = c.id
         ORDER BY a.date_avis DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
}
// Dans la partie PHP, après les autres sections
elseif ($section === 'messages') {
    // Filtres
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $repondu = isset($_GET['repondu']) ? $_GET['repondu'] : '';
    $date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
    $date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

    // Construction de la requête
    $query = "SELECT * FROM messages_contact WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (nom LIKE ? OR email LIKE ? OR message LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($repondu === 'oui') {
        $query .= " AND reponse IS NOT NULL";
    } elseif ($repondu === 'non') {
        $query .= " AND reponse IS NULL";
    }
    
    if (!empty($date_debut) && !empty($date_fin)) {
        $query .= " AND date_envoi BETWEEN ? AND ?";
        $params[] = $date_debut;
        $params[] = $date_fin;
    }
    
    $query .= " ORDER BY date_envoi DESC LIMIT $items_per_page OFFSET $offset";
    
    // Exécution de la requête
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Requête pour le nombre total d'éléments
    $count_query = "SELECT COUNT(*) FROM messages_contact WHERE 1=1";
    
    if (!empty($search)) {
        $count_query .= " AND (nom LIKE ? OR email LIKE ? OR message LIKE ?)";
    }
    
    if ($repondu === 'oui') {
        $count_query .= " AND reponse IS NOT NULL";
    } elseif ($repondu === 'non') {
        $count_query .= " AND reponse IS NULL";
    }
    
    if (!empty($date_debut) && !empty($date_fin)) {
        $count_query .= " AND date_envoi BETWEEN ? AND ?";
    }
    
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_items = $stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);
    // Récupérer tous les messages de contact
    $messages = $pdo->query("SELECT * FROM messages_contact ORDER BY date_envoi DESC")->fetchAll(PDO::FETCH_ASSOC);

    // Traitement de la réponse
    if (isset($_POST['repondre'])) {
        $message_id = intval($_POST['message_id']);
        $reponse = htmlspecialchars(trim($_POST['reponse']));

        if ($reponse) {
            try {
                // Mettre à jour le message avec la réponse
                $stmt = $pdo->prepare("UPDATE messages_contact SET reponse = ?, date_reponse = NOW() WHERE id = ?");
                $stmt->execute([$reponse, $message_id]);
                $_SESSION['success'] = "Réponse envoyée avec succès";
                header('Location: admin.php?section=messages');
                exit;
            } catch (Exception $e) {
                $_SESSION['error'] = "Erreur lors de l'envoi de la réponse";
                header('Location: admin.php?section=messages');
                exit;
            }
        } else {
            $_SESSION['error'] = "Le champ réponse ne peut pas être vide";
            header('Location: admin.php?section=messages');
            exit;
        }
    }

    // Suppression d'un message
    if (isset($_GET['delete_message'])) {
        $id = intval($_GET['delete_message']);
        $pdo->prepare("DELETE FROM messages_contact WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "Message supprimé avec succès";
        header('Location: admin.php?section=messages');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Admin - Tableau de bord</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.1);
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

        .stat-card {
            background: var(--primary-color);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h2 {
            font-size: 2.5rem;
            margin-bottom: 5px;
        }

        .table thead {
            background: var(--primary-color);
            color: white;
        }

        .badge.bg-warning {
            color: #000 !important;
        }

        /* Styles pour les boutons d'action */
        .btn-action {
            padding: 0.35rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            transition: all 0.2s;
            width: 100%;
            margin-bottom: 0.3rem;
        }

        .btn-action i {
            font-size: 0.9rem;
        }

        .btn-approve {
            background-color: #28a745;
            color: white;
            border: 1px solid #28a745;
        }

        .btn-approve:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .btn-refuse {
            background-color: #dc3545;
            color: white;
            border: 1px solid #dc3545;
        }

        .btn-refuse:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }

        .btn-reset {
            background-color: #ffc107;
            color: #212529;
            border: 1px solid #ffc107;
        }

        .btn-reset:hover {
            background-color: #e0a800;
            border-color: #d39e00;
        }

        .btn-delete {
            background-color: #6c757d;
            color: white;
            border: 1px solid #6c757d;
        }

        .btn-delete:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }

        .btn-confirm {
            background-color: #17a2b8;
            color: white;
            border: 1px solid #17a2b8;
        }

        .btn-confirm:hover {
            background-color: #138496;
            border-color: #117a8b;
        }

        .btn-complete {
            background-color: #28a745;
            color: white;
            border: 1px solid #28a745;
        }

        .btn-cancel {
            background-color: #dc3545;
            color: white;
            border: 1px solid #dc3545;
        }

        .btn-refund {
            background-color: #6f42c1;
            color: white;
            border: 1px solid #6f42c1;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            min-width: 120px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }

            .sidebar-brand span,
            .sidebar-nav a span {
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

        .modal-img {
            max-height: 200px;
            object-fit: contain;
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
        <li><a href="?section=dashboard" class="<?= $section === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Tableau de bord</span>
        </a></li>
        <li><a href="?section=utilisateurs" class="<?= $section === 'utilisateurs' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Utilisateurs</span>
        </a></li>
        <li><a href="?section=colis" class="<?= $section === 'colis' ? 'active' : '' ?>">
            <i class="fas fa-box"></i>
            <span>Colis</span>
        </a></li>
        <li><a href="?section=voyages" class="<?= $section === 'voyages' ? 'active' : '' ?>">
            <i class="fas fa-route"></i>
            <span>Voyages</span>
        </a></li>
        <li><a href="?section=transporteurs" class="<?= $section === 'transporteurs' ? 'active' : '' ?>">
            <i class="fas fa-truck-moving"></i>
            <span>Transporteurs</span>
        </a></li>
        <li><a href="?section=paiements" class="<?= $section === 'paiements' ? 'active' : '' ?>">
            <i class="fas fa-money-bill-wave"></i>
            <span>Paiements</span>
        </a></li>
        <li><a href="?section=avis" class="<?= $section === 'avis' ? 'active' : '' ?>">
            <i class="fas fa-star"></i>
            <span>Avis</span>
        </a></li>
        <li><a href="?section=messages" class="<?= $section === 'messages' ? 'active' : '' ?>">
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
        <?php if ($section === 'dashboard'): ?>
            <h1 class="mb-4 text-center text-primary fw-bold">Tableau de bord administrateur</h1>
            <div class="row g-4 mb-4">
                <div class="col-md-2">
                    <div class="stat-card">
                        <h2><?= $nb_users ?></h2>
                        <p>Utilisateurs</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <h2><?= $nb_colis ?></h2>
                        <p>Colis</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <h2><?= $nb_voyages ?></h2>
                        <p>Voyages</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <h2><?= $nb_transporteurs ?></h2>
                        <p>Transporteurs</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <h2><?= $nb_paiements ?></h2>
                        <p>Paiements</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <h2><?= $nb_avis ?></h2>
                        <p>Avis</p>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Revenus totaux</h5>
                        </div>
                        <div class="card-body text-center">
                            <h2 class="text-success"><?= number_format($total_paiements, 2) ?> €</h2>
                            <p class="text-muted">Total des paiements complétés</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Derniers colis</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $last_colis = $pdo->query("SELECT c.*, u.nom, u.prenom FROM colis c LEFT JOIN users u ON c.user_id = u.id ORDER BY c.date_post DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                            if (!empty($last_colis)): ?>
                                <ul class="list-group">
                                    <?php foreach ($last_colis as $c): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($c['nom_colis']) ?></strong>
                                                <small class="d-block text-muted"><?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?></small>
                                            </div>
                                            <span class="badge <?= $c['statut'] == 'en_attente' ? 'bg-warning' : ($c['statut'] == 'approuve' ? 'bg-success' : 'bg-danger') ?>">
                                                <?= $c['statut'] == 'en_attente' ? 'En attente' : ($c['statut'] == 'approuve' ? 'Approuvé' : 'Refusé') ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">Aucun colis récent</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Derniers paiements</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $last_paiements = $pdo->query("SELECT p.*, u.nom, u.prenom FROM paiements p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.date_paiement DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                            if (!empty($last_paiements)): ?>
                                <ul class="list-group">
                                    <?php foreach ($last_paiements as $p): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></strong>
                                                <small class="d-block text-muted"><?= number_format($p['montant'], 2) ?> €</small>
                                            </div>
                                            <span class="badge <?= $p['statut'] == 'complete' ? 'bg-success' : ($p['statut'] == 'en_attente' ? 'bg-warning' : 'bg-danger') ?>">
                                                <?= $p['statut'] == 'complete' ? 'Complété' : ($p['statut'] == 'en_attente' ? 'En attente' : 'Annulé') ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">Aucun paiement récent</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($section === 'utilisateurs'): ?>
            <h2 class="mb-4 text-center text-primary fw-bold">Gestion des utilisateurs</h2>
            <div class="table-responsive">
                <table class="table table-bordered align-middle shadow-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Date inscription</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td>
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#modalUser<?= $u['id'] ?>">
                                        <?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['telephone']) ?></td>
                                <td><?= htmlspecialchars($u['date_inscription']) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?section=utilisateurs&delete=<?= $u['id'] ?>" class="btn btn-action btn-delete" onclick="return confirm('Supprimer cet utilisateur ?')">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            <div class="modal fade" id="modalUser<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Profil de <?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row mb-3">
                                                <?php if ($u['photo_profil']): ?>
                                                    <div class="col-md-3 text-center">
                                                        <img src="<?= htmlspecialchars('../' . $u['photo_profil'] . '') ?>" class="img-fluid rounded-circle mb-3" style="width:120px;height:120px;object-fit:cover;">
                                                    </div>
                                                <?php endif; ?>
                                                <div class="<?= $u['photo_profil'] ? 'col-md-9' : 'col-12' ?>">
                                                    <ul class="list-group list-group-flush">
                                                        <li class="list-group-item"><strong>Email:</strong> <?= htmlspecialchars($u['email']) ?></li>
                                                        <li class="list-group-item"><strong>Téléphone:</strong> <?= htmlspecialchars($u['telephone']) ?></li>
                                                        <li class="list-group-item"><strong>Date inscription:</strong> <?= htmlspecialchars($u['date_inscription']) ?></li>
                                                        <li class="list-group-item"><strong>Rôle:</strong>
                                                            <?php
                                                            if ($u['role'] == 'admin') echo '<span class="badge bg-danger">Administrateur</span>';
                                                            elseif ($u['role'] == 'transporteur') echo '<span class="badge bg-primary">Transporteur</span>';
                                                            else echo '<span class="badge bg-secondary">Utilisateur</span>';
                                                            ?>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>

                                            <?php
                                            $stmt = $pdo->prepare("SELECT 
                                            COUNT(*) as total,
                                            SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
                                            SUM(CASE WHEN statut = 'approuve' THEN 1 ELSE 0 END) as approuves,
                                            SUM(CASE WHEN statut = 'refuse' THEN 1 ELSE 0 END) as refuses
                                            FROM colis WHERE user_id = ?");
                                            $stmt->execute([$u['id']]);
                                            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                                            ?>

                                            <h5 class="mb-3"><i class="fas fa-box"></i> Statistiques des colis</h5>
                                            <div class="row mb-4">
                                                <div class="col-md-3 mb-2">
                                                    <div class="card bg-light">
                                                        <div class="card-body text-center">
                                                            <h5 class="card-title"><?= $stats['total'] ?></h5>
                                                            <p class="card-text">Total</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3 mb-2">
                                                    <div class="card bg-warning text-dark">
                                                        <div class="card-body text-center">
                                                            <h5 class="card-title"><?= $stats['en_attente'] ?></h5>
                                                            <p class="card-text">En attente</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3 mb-2">
                                                    <div class="card bg-success text-white">
                                                        <div class="card-body text-center">
                                                            <h5 class="card-title"><?= $stats['approuves'] ?></h5>
                                                            <p class="card-text">Approuvés</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3 mb-2">
                                                    <div class="card bg-danger text-white">
                                                        <div class="card-body text-center">
                                                            <h5 class="card-title"><?= $stats['refuses'] ?></h5>
                                                            <p class="card-text">Refusés</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php if ($u['role'] == 'transporteur'): ?>
                                                <?php
                                                $stmt = $pdo->prepare("SELECT * FROM transporteurs WHERE user_id = ?");
                                                $stmt->execute([$u['id']]);
                                                $transporteur = $stmt->fetch(PDO::FETCH_ASSOC);
                                                ?>

                                                <h5 class="mb-3"><i class="fas fa-truck"></i> Informations transporteur</h5>
                                                <div class="card mb-4">
                                                    <div class="card-body">
                                                        <?php if ($transporteur): ?>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <p><strong>Compagnie:</strong> <?= htmlspecialchars($transporteur['compagnie']) ?></p>
                                                                    <p><strong>Véhicule:</strong> <?= htmlspecialchars($transporteur['vehicule']) ?></p>
                                                                    <p><strong>Numéro permis:</strong> <?= htmlspecialchars($transporteur['numero_permis']) ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <p><strong>Adresse:</strong> <?= htmlspecialchars($transporteur['adresse']) ?></p>
                                                                    <p><strong>Ville:</strong> <?= htmlspecialchars($transporteur['ville']) ?></p>
                                                                    <p><strong>Pays:</strong> <?= htmlspecialchars($transporteur['pays']) ?></p>
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <p class="text-muted">Aucune information de transporteur enregistrée</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($section === 'colis'): ?>
    <h2 class="mb-4 text-center text-primary fw-bold">Gestion des colis</h2>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Formulaire de filtrage -->
    <form method="GET" action="admin.php" class="mb-4">
        <input type="hidden" name="section" value="colis">
        <div class="row g-2">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="statut" class="form-select">
                    <option value="">Tous statuts</option>
                    <option value="en_attente" <?= $statut === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                    <option value="approuve" <?= $statut === 'approuve' ? 'selected' : '' ?>>Approuvé</option>
                    <option value="refuse" <?= $statut === 'refuse' ? 'selected' : '' ?>>Refusé</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="type_produit" class="form-select">
                    <option value="">Tous types</option>
                    <?php
                    $types = $pdo->query("SELECT DISTINCT type_produit FROM colis WHERE type_produit IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($types as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $t === $type_produit ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_debut" class="form-control" value="<?= htmlspecialchars($date_debut) ?>" placeholder="Date début">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_fin" class="form-control" value="<?= htmlspecialchars($date_fin) ?>" placeholder="Date fin">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered align-middle shadow-sm">            <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom colis</th>
                            <th>Utilisateur</th>
                            <th>Type</th>
                            <th>Poids</th>
                            <th>Statut</th>
                            <th>Livraison</th>
                            <th>Date post</th>
                            <th>Action</th>
                            <th>Confirmation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($colis as $c): ?>
                            <tr>
                                <td><?= $c['id'] ?></td>
                                <td>
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#modalColis<?= $c['id'] ?>">
                                        <?= htmlspecialchars($c['nom_colis']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?></td>
                                <td><?= htmlspecialchars($c['type_produit']) ?></td>
                                <td><?= htmlspecialchars($c['poids']) ?></td>
                                <td>
                                    <?php
                                    if ($c['statut'] == 'en_attente') echo '<span class="badge bg-warning text-dark">En attente</span>';
                                    elseif ($c['statut'] == 'approuve') echo '<span class="badge bg-primary">Approuvé</span>';
                                    elseif ($c['statut'] == 'refuse') echo '<span class="badge bg-danger">Refusé</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $statut_livraison = !empty($c['statut_livraison']) ? $c['statut_livraison'] : 'En attente';

                                    if ($statut_livraison == 'En attente') echo '<span class="badge bg-secondary">En attente</span>';
                                    elseif ($statut_livraison == 'En cours') echo '<span class="badge bg-info">En cours</span>';
                                    elseif ($statut_livraison == 'Livré') echo '<span class="badge bg-success">Livré</span>';
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($c['date_post']) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($c['statut'] == 'en_attente'): ?>
                                            <a href="?section=colis&approuve=<?= $c['id'] ?>" class="btn btn-action btn-approve">
                                                <i class="fas fa-check"></i> Approuver
                                            </a>
                                            <a href="?section=colis&refuse=<?= $c['id'] ?>" class="btn btn-action btn-refuse">
                                                <i class="fas fa-times"></i> Refuser
                                            </a>
                                        <?php elseif ($c['statut'] == 'approuve' || $c['statut'] == 'refuse'): ?>
                                            <a href="?section=colis&reset_status=<?= $c['id'] ?>" class="btn btn-action btn-reset">
                                                <i class="fas fa-undo"></i> Réinitialiser
                                            </a>
                                        <?php endif; ?>
                                        <a href="?section=colis&delete=<?= $c['id'] ?>"
                                            class="btn btn-action btn-delete <?= (!empty($c['statut_livraison']) && $c['statut_livraison'] == 'En cours' ? 'disabled' : '') ?>"
                                            onclick="return confirm('Supprimer ce colis ?')">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <?php if (isset($c['demande_livraison_id']) && $c['demande_livraison_id']): ?>
                                        <div class="action-buttons">
                                            <a href="?section=colis&confirmer_livraison=<?= $c['demande_livraison_id'] ?>" class="btn btn-action btn-confirm">
                                                <i class="fas fa-check"></i> Confirmer
                                            </a>
                                            <a href="?section=colis&refuser_livraison=<?= $c['demande_livraison_id'] ?>" class="btn btn-action btn-refuse">
                                                <i class="fas fa-times"></i> Refuser
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <div class="modal fade" id="modalColis<?= $c['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Détails du colis: <?= htmlspecialchars($c['nom_colis']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <?php if ($c['image_colis']): ?>
                                                    <div class="col-md-4 mb-3">
                                                        <img src="<?= htmlspecialchars('../' . $c['image_colis'] . '') ?>" class="img-fluid rounded modal-img">
                                                    </div>
                                                <?php endif; ?>
                                                <div class="<?= $c['image_colis'] ? 'col-md-8' : 'col-12' ?>">
                                                    <ul class="list-group list-group-flush">
                                                        <li class="list-group-item"><strong>Propriétaire:</strong> <?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?></li>
                                                        <li class="list-group-item"><strong>Type:</strong> <?= htmlspecialchars($c['type_produit']) ?></li>
                                                        <li class="list-group-item"><strong>Nombre produits:</strong> <?= htmlspecialchars($c['nombre_produits']) ?></li>
                                                        <li class="list-group-item"><strong>Poids:</strong> <?= htmlspecialchars($c['poids']) ?> kg</li>
                                                        <li class="list-group-item"><strong>Dimensions:</strong> <?= htmlspecialchars($c['dimensions']) ?></li>
                                                        <li class="list-group-item"><strong>De:</strong> <?= htmlspecialchars($c['adresse_depart']) ?></li>
                                                        <li class="list-group-item"><strong>À:</strong> <?= htmlspecialchars($c['adresse_destination']) ?></li>
                                                        <li class="list-group-item"><strong>Prix estimé:</strong> <?= htmlspecialchars($c['prix_estime']) ?> €</li>
                                                        <li class="list-group-item"><strong>Date limite:</strong> <?= htmlspecialchars($c['date_limite']) ?></li>
                                                        <li class="list-group-item"><strong>Numéro suivi:</strong> <?= htmlspecialchars($c['numero_suivi']) ?></li>
                                                        <li class="list-group-item"><strong>Statut:</strong>
                                                            <?php
                                                            if ($c['statut'] == 'en_attente') echo '<span class="badge bg-warning text-dark">En attente</span>';
                                                            elseif ($c['statut'] == 'approuve') echo '<span class="badge bg-primary">Approuvé</span>';
                                                            elseif ($c['statut'] == 'refuse') echo '<span class="badge bg-danger">Refusé</span>';
                                                            ?>
                                                        </li>
                                                        <li class="list-group-item"><strong>Livraison:</strong>
                                                            <?php
                                                            $statut_livraison = !empty($c['statut_livraison']) ? $c['statut_livraison'] : 'En attente';

                                                            if ($statut_livraison == 'En attente') echo '<span class="badge bg-secondary">En attente</span>';
                                                            elseif ($statut_livraison == 'En cours') echo '<span class="badge bg-info">En cours</span>';
                                                            elseif ($statut_livraison == 'Livré') echo '<span class="badge bg-success">Livré</span>';
                                                            ?>
                                                        </li>

                                                        <?php
                                                        $stmt = $pdo->prepare("SELECT u.id, u.nom, u.prenom, u.email, u.telephone 
                                                        FROM reservations r 
                                                        JOIN voyages v ON r.voyage_id = v.id 
                                                        JOIN users u ON v.user_id = u.id 
                                                        WHERE r.colis_id = ? AND r.statut = 'accepte'");
                                                        $stmt->execute([$c['id']]);
                                                        $transporteurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                        ?>

                                                        <?php if (!empty($transporteurs)): ?>
                                                            <li class="list-group-item">
                                                                <strong>Transporteur(s):</strong>
                                                                <div class="mt-2">
                                                                    <?php foreach ($transporteurs as $t): ?>
                                                                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                                                            <div>
                                                                                <?= htmlspecialchars($t['prenom'] . ' ' . $t['nom']) ?>
                                                                                <small class="text-muted d-block"><?= htmlspecialchars($t['email']) ?></small>
                                                                                <small class="text-muted d-block"><?= htmlspecialchars($t['telephone']) ?></small>
                                                                            </div>
                                                                            <a href="../messagerie.php?destinataire_id=<?= $t['id'] ?>"
                                                                                class="btn btn-sm btn-primary">
                                                                                <i class="fas fa-envelope"></i> Contacter
                                                                            </a>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?section=colis&page=<?= $i ?>&search=<?= urlencode($search) ?>&statut=<?= urlencode($statut) ?>&type_produit=<?= urlencode($type_produit) ?>&date_debut=<?= urlencode($date_debut) ?>&date_fin=<?= urlencode($date_fin) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>


        <?php elseif ($section === 'voyages'): ?>
            <h2 class="mb-4 text-center text-primary fw-bold">Gestion des voyages</h2>
            <div class="table-responsive">
                <table class="table table-bordered align-middle shadow-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Utilisateur</th>
                            <th>Départ</th>
                            <th>Destination</th>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Poids max</th>
                            <th>Date post</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($voyages as $v): ?>
                            <tr>
                                <td><?= $v['id'] ?></td>
                                <td><?= htmlspecialchars($v['prenom'] . ' ' . $v['nom']) ?></td>
                                <td><?= htmlspecialchars($v['pays_depart']) ?></td>
                                <td><?= htmlspecialchars($v['pays_destination']) ?></td>
                                <td><?= htmlspecialchars($v['date_depart']) ?></td>
                                <td><?= htmlspecialchars($v['heure_depart']) ?></td>
                                <td><?= htmlspecialchars($v['poids_max']) ?></td>
                                <td><?= htmlspecialchars($v['date_post']) ?></td>
                                <td>
                                    <?php
                                    if (!isset($v['statut'])) $v['statut'] = 'en_attente';
                                    if ($v['statut'] == 'en_attente') echo '<span class="badge bg-warning text-dark">En attente</span>';
                                    elseif ($v['statut'] == 'approuve') echo '<span class="badge bg-success">Approuvé</span>';
                                    elseif ($v['statut'] == 'refuse') echo '<span class="badge bg-danger">Refusé</span>';
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if (!isset($v['statut']) || $v['statut'] == 'en_attente'): ?>
                                            <a href="?section=voyages&approuve=<?= $v['id'] ?>" class="btn btn-action btn-approve">
                                                <i class="fas fa-check"></i> Approuver
                                            </a>
                                            <a href="?section=voyages&refuse=<?= $v['id'] ?>" class="btn btn-action btn-refuse" onclick="return confirm('Refuser ce voyage ?')">
                                                <i class="fas fa-times"></i> Refuser
                                            </a>
                                        <?php endif; ?>

                                        <a href="?section=voyages&delete=<?= $v['id'] ?>" class="btn btn-action btn-delete" onclick="return confirm('Supprimer ce voyage ?')">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($section === 'transporteurs'): ?>
            <h2 class="mb-4 text-center text-primary fw-bold">Gestion des transporteurs</h2>

            <!-- Filtres -->
            <form method="GET" action="admin.php" class="mb-4">
                <input type="hidden" name="section" value="transporteurs">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Rechercher par nom" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-4">
                        <select name="country" class="form-select">
                            <option value="">-- Filtrer par pays --</option>
                            <?php
                            $countries = $pdo->query("SELECT DISTINCT pays FROM transporteurs WHERE pays IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($countries as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= $c === $country ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered align-middle shadow-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Permis</th>
                            <th>Véhicule</th>
                            <th>Compagnie</th>
                            <th>Pays</th>
                            <th>Date création</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transporteurs as $t): ?>
                            <tr>
                                <td><?= $t['id'] ?></td>
                                <td><?= htmlspecialchars($t['prenom'] . ' ' . $t['nom']) ?></td>
                                <td><?= htmlspecialchars($t['email']) ?></td>
                                <td><?= htmlspecialchars($t['telephone']) ?></td>
                                <td><?= htmlspecialchars($t['numero_permis']) ?></td>
                                <td><?= htmlspecialchars($t['vehicule']) ?></td>
                                <td><?= htmlspecialchars($t['compagnie']) ?></td>
                                <td><?= htmlspecialchars($t['pays']) ?></td>
                                <td><?= htmlspecialchars($t['date_creation']) ?></td>
                                <td>
                                    <a href="?section=transporteurs&delete=<?= $t['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce transporteur ?')">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transporteurs)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted">Aucun transporteur trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?section=transporteurs&page=<?= $i ?>&search=<?= urlencode($search) ?>&country=<?= urlencode($country) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>

        <?php elseif ($section === 'paiements'): ?>
            <h2 class="mb-4 text-center text-primary fw-bold">Gestion des paiements</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h3><?= number_format($stats_paiements['montant_total'], 2) ?> €</h3>
                            <p class="mb-0">Total payé</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3><?= $stats_paiements['payes'] ?></h3>
                            <p class="mb-0">Paiements réussis</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body text-center">
                            <h3><?= $stats_paiements['en_attente'] ?></h3>
                            <p class="mb-0">En attente</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h3><?= $stats_paiements['echecs'] + $stats_paiements['annules'] ?></h3>
                            <p class="mb-0">Échecs/Annulés</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle shadow-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Utilisateur</th>
                            <th>Colis</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Référence</th>
                            <th>Opérateur</th>
                            <th>Date création</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paiements as $p): ?>
                            <tr>
                                <td><?= $p['id'] ?></td>
                                <td>
                                    <?= htmlspecialchars($p['user_prenom'] . ' ' . $p['user_nom']) ?>
                                    <small class="d-block text-muted"><?= htmlspecialchars($p['user_email']) ?></small>
                                </td>
                                <td>
                                    <?= !empty($p['nom_colis']) ? htmlspecialchars($p['nom_colis']) : 'N/A' ?>
                                    <small class="d-block text-muted"><?= !empty($p['prix_estime']) ? htmlspecialchars($p['prix_estime']) . ' €' : '' ?></small>
                                </td>
                                <td><?= number_format($p['montant'], 2) ?> €</td>
                                <td><?= htmlspecialchars($p['methode_paiement']) ?></td>
                                <td><?= htmlspecialchars($p['reference']) ?></td>
                                <td><?= htmlspecialchars($p['operateur']) ?></td>
                                <td><?= htmlspecialchars($p['date_creation']) ?></td>
                                <td>
                                    <?php
                                    if ($p['statut'] == 'paye') echo '<span class="badge bg-success">Payé</span>';
                                    elseif ($p['statut'] == 'en_attente') echo '<span class="badge bg-warning">En attente</span>';
                                    elseif ($p['statut'] == 'echec') echo '<span class="badge bg-danger">Échec</span>';
                                    elseif ($p['statut'] == 'annule') echo '<span class="badge bg-secondary">Annulé</span>';
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($p['statut'] == 'en_attente'): ?>
                                            <a href="?section=paiements&confirmer=<?= $p['id'] ?>" class="btn btn-action btn-confirm">
                                                <i class="fas fa-check"></i> Confirmer
                                            </a>
                                            <a href="?section=paiements&annuler=<?= $p['id'] ?>" class="btn btn-action btn-refuse">
                                                <i class="fas fa-times"></i> Annuler
                                            </a>
                                        <?php endif; ?>
                                        <a href="?section=paiements&delete=<?= $p['id'] ?>" class="btn btn-action btn-delete" onclick="return confirm('Supprimer ce paiement ?')">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($section === 'avis'): ?>
            <h2 class="mb-4 text-center text-primary fw-bold">Gestion des avis</h2>
            <div class="table-responsive">
                <table class="table table-bordered align-middle shadow-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Utilisateur</th>
                            <th>Transporteur</th>
                            <th>Colis</th>
                            <th>Note</th>
                            <th>Commentaire</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($avis as $a): ?>
                            <tr>
                                <td><?= $a['id'] ?></td>
                                <td><?= htmlspecialchars($a['user_prenom'] . ' ' . $a['user_nom']) ?></td>
                                <td><?= htmlspecialchars($a['transporteur_prenom'] . ' ' . $a['transporteur_nom']) ?></td>
                                <td><?= !empty($a['nom_colis']) ? htmlspecialchars($a['nom_colis']) : 'N/A' ?></td>
                                <td>
                                    <?php
                                    $note = $a['note'];
                                    echo str_repeat('<i class="fas fa-star text-warning"></i>', $note);
                                    echo str_repeat('<i class="far fa-star text-warning"></i>', 5 - $note);
                                    ?>
                                </td>
                                <td><?= !empty($a['commentaire']) ? htmlspecialchars($a['commentaire']) : 'Aucun commentaire' ?></td>
                                <td><?= htmlspecialchars($a['date_avis']) ?></td>
                                <td>
                                    <?php
                                    if ($a['statut'] == 'en_attente') echo '<span class="badge bg-warning text-dark">En attente</span>';
                                    elseif ($a['statut'] == 'approuve') echo '<span class="badge bg-success">Approuvé</span>';
                                    elseif ($a['statut'] == 'refuse') echo '<span class="badge bg-danger">Refusé</span>';
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($a['statut'] == 'en_attente'): ?>
                                            <a href="?section=avis&approuve=<?= $a['id'] ?>" class="btn btn-action btn-approve">
                                                <i class="fas fa-check"></i> Approuver
                                            </a>
                                            <a href="?section=avis&refuse=<?= $a['id'] ?>" class="btn btn-action btn-refuse">
                                                <i class="fas fa-times"></i> Refuser
                                            </a>
                                        <?php endif; ?>
                                        <a href="?section=avis&delete=<?= $a['id'] ?>" class="btn btn-action btn-delete" onclick="return confirm('Supprimer cet avis ?')">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($avis)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">Aucun avis à modérer.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($section === 'messages'): ?>
            <h2 class="mb-4 text-center text-primary fw-bold">Messages de contact</h2>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-bordered align-middle shadow-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Message</th>
                            <th>Date</th>
                            <th>Réponse</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $m): ?>
                            <tr>
                                <td><?= $m['id'] ?></td>
                                <td><?= htmlspecialchars($m['nom']) ?></td>
                                <td><?= htmlspecialchars($m['email']) ?></td>
                                <td><?= htmlspecialchars($m['message']) ?></td>
                                <td><?= htmlspecialchars($m['date_envoi']) ?></td>
                                <td>
                                    <?php if (!empty($m['reponse'])): ?>
                                        <?= htmlspecialchars($m['reponse']) ?>
                                        <small class="text-muted d-block">Répondu le: <?= htmlspecialchars($m['date_reponse']) ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-warning">En attente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-action btn-confirm" data-bs-toggle="modal" data-bs-target="#modalReponse<?= $m['id'] ?>">
                                            <i class="fas fa-reply"></i> Répondre
                                        </button>
                                        <a href="?section=messages&delete_message=<?= $m['id'] ?>" class="btn btn-action btn-delete" onclick="return confirm('Supprimer ce message ?')">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            <!-- Modal pour répondre -->
                            <div class="modal fade" id="modalReponse<?= $m['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Répondre à <?= htmlspecialchars($m['nom']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="post" action="?section=messages">
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Message original:</label>
                                                    <p><?= htmlspecialchars($m['message']) ?></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="reponse<?= $m['id'] ?>" class="form-label">Votre réponse:</label>
                                                    <textarea id="reponse<?= $m['id'] ?>" name="reponse" class="form-control" rows="5" required><?= !empty($m['reponse']) ? htmlspecialchars($m['reponse']) : '' ?></textarea>
                                                </div>
                                                <input type="hidden" name="message_id" value="<?= $m['id'] ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                <button type="submit" name="repondre" class="btn btn-primary">Envoyer la réponse</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($messages)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Aucun message à afficher.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>


    </div>

    <script src="../bootstrap-5.3.3-dist/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>