-- Migration 004 : correction commission 95% transporteur / 5% admin + système retraits automatiques
-- A exécuter après 003

SET FOREIGN_KEY_CHECKS=0;

-- Table portefeuille admin (single row id=1)
CREATE TABLE IF NOT EXISTS `admin_wallet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `solde` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insérer ligne initiale si absente
INSERT IGNORE INTO `admin_wallet` (`id`, `solde`) VALUES (1, 0.00);

-- Table retraits : historique des retraits transporteur et admin, + paiements automatiques après livraison
CREATE TABLE IF NOT EXISTS `retraits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'transporteur ou admin',
  `type` enum('transporteur','admin') NOT NULL DEFAULT 'transporteur',
  `montant` decimal(12,2) NOT NULL,
  `frais` decimal(12,2) NOT NULL DEFAULT 0.00,
  `montant_net` decimal(12,2) NOT NULL COMMENT 'montant - frais',
  `statut` enum('en_attente','approuve','refuse','paye','echec') NOT NULL DEFAULT 'en_attente',
  `methode` enum('mobile_money','virement','kkiapay','autre') NOT NULL DEFAULT 'mobile_money',
  `numero` varchar(100) DEFAULT NULL COMMENT 'numéro mobile money du transporteur',
  `operateur` varchar(50) DEFAULT NULL COMMENT 'mtn, moov, orange, etc',
  `reference` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `colis_id` int(11) DEFAULT NULL COMMENT 'colis lié si paiement automatique',
  `date_demande` datetime NOT NULL DEFAULT current_timestamp(),
  `date_traitement` datetime DEFAULT NULL,
  `traite_par` int(11) DEFAULT NULL COMMENT 'admin qui a traité',
  `kkiapay_response` text DEFAULT NULL COMMENT 'réponse API kkiapay payout',
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `user_id` (`user_id`),
  KEY `colis_id` (`colis_id`),
  KEY `statut` (`statut`),
  CONSTRAINT `retraits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `retraits_ibfk_2` FOREIGN KEY (`colis_id`) REFERENCES `colis` (`id`) ON DELETE SET NULL,
  CONSTRAINT `retraits_ibfk_3` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
