-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : jeu. 31 juil. 2025 à 14:02
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `transport_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `avis`
--

CREATE TABLE `avis` (
  `id` int(11) NOT NULL,
  `transporteur_id` int(11) NOT NULL COMMENT 'ID du transporteur évalué',
  `user_id` int(11) NOT NULL COMMENT 'ID de l''utilisateur qui poste l''avis',
  `colis_id` int(11) DEFAULT NULL COMMENT 'ID du colis concerné (optionnel)',
  `note` tinyint(1) NOT NULL COMMENT 'Note de 1 à 5',
  `commentaire` text DEFAULT NULL COMMENT 'Commentaire de l''utilisateur',
  `date_avis` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Date de publication de l''avis',
  `statut` enum('en_attente','approuve','refuse') DEFAULT 'en_attente' COMMENT 'Statut de modération'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table des avis sur les transporteurs';

-- --------------------------------------------------------

--
-- Structure de la table `colis`
--

CREATE TABLE `colis` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `nom_colis` varchar(100) NOT NULL,
  `image_colis` varchar(255) DEFAULT NULL,
  `type_produit` varchar(50) DEFAULT NULL,
  `nombre_produits` int(11) DEFAULT NULL,
  `poids` decimal(6,2) DEFAULT NULL,
  `dimensions` varchar(50) DEFAULT NULL,
  `pays` varchar(100) DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `date_limite` date DEFAULT NULL,
  `adresse_depart` varchar(255) DEFAULT NULL,
  `adresse_destination` varchar(255) DEFAULT NULL,
  `prix_estime` decimal(8,2) DEFAULT NULL,
  `date_post` datetime DEFAULT current_timestamp(),
  `statut` enum('en_attente','approuve','refuse') DEFAULT 'en_attente',
  `numero_suivi` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `colis`
--

INSERT INTO `colis` (`id`, `user_id`, `nom_colis`, `image_colis`, `type_produit`, `nombre_produits`, `poids`, `dimensions`, `pays`, `ville`, `date_limite`, `adresse_depart`, `adresse_destination`, `prix_estime`, `date_post`, `statut`, `numero_suivi`) VALUES
(31, 6, 'Techno pop 10', 'uploads/colis_6888b4a5838bc.jpg', 'documents', 15, 15.00, '5 5 5', 'Bénin', 'Porto novo', '5252-02-12', 'cotonou', 'porto novo', 19200.00, '2025-07-29 12:46:45', 'approuve', 'COLIS6888b4a589c1c');

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `expediteur_id` int(11) NOT NULL,
  `destinataire_id` int(11) NOT NULL,
  `colis_id` int(11) DEFAULT NULL,
  `voyage_id` int(11) DEFAULT NULL,
  `contenu` text NOT NULL,
  `date_envoi` datetime DEFAULT current_timestamp(),
  `lu` tinyint(1) DEFAULT 0,
  `fichier` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id`, `expediteur_id`, `destinataire_id`, `colis_id`, `voyage_id`, `contenu`, `date_envoi`, `lu`, `fichier`) VALUES
(1, 1, 1, 1, 1, 'salut', '2025-06-22 10:34:00', 0, NULL),
(19, 3, 3, NULL, NULL, 'salut', '2025-07-20 14:14:13', 0, NULL),
(26, 6, 6, NULL, NULL, 'hbb,b', '2025-07-29 12:49:27', 0, NULL),
(27, 6, 6, NULL, NULL, '[ Agence de Transport de Colis.png]', '2025-07-30 12:44:55', 0, 'uploads/messages/msg_688a05b74b940.png');

-- --------------------------------------------------------

--
-- Structure de la table `messages_contact`
--

CREATE TABLE `messages_contact` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `reponse` text DEFAULT NULL,
  `date_reponse` datetime DEFAULT NULL,
  `lu_par_admin` tinyint(1) DEFAULT 0,
  `lu_par_utilisateur` tinyint(1) DEFAULT 0,
  `date_envoi` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paiements`
--

CREATE TABLE `paiements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `colis_id` int(11) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `methode_paiement` enum('mobile_money','carte_credit','virement','autre') DEFAULT NULL,
  `numero_transaction` varchar(50) DEFAULT NULL,
  `operateur` varchar(50) DEFAULT NULL,
  `reference` varchar(50) NOT NULL,
  `details_paiement` text DEFAULT NULL,
  `statut` enum('en_attente','paye','echec','annule') DEFAULT 'en_attente',
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_paiement` datetime DEFAULT NULL,
  `ip_client` varchar(45) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `paiements`
--

INSERT INTO `paiements` (`id`, `user_id`, `colis_id`, `montant`, `methode_paiement`, `numero_transaction`, `operateur`, `reference`, `details_paiement`, `statut`, `date_creation`, `date_paiement`, `ip_client`, `device_info`) VALUES
(5, 6, 31, 19200.00, 'carte_credit', 'CAR1753789631909', NULL, 'PAY6888b4a5a4570', '{\"methode\":\"carte_credit\",\"montant\":\"19200.00\",\"numero_masque\":\"1234************4244\",\"expiration\":\"12\\/25\"}', 'paye', '2025-07-29 12:46:45', '2025-07-29 12:47:11', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0');

-- --------------------------------------------------------

--
-- Structure de la table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `colis_id` int(11) DEFAULT NULL,
  `voyage_id` int(11) DEFAULT NULL,
  `statut` enum('en_attente','accepte','refuse','termine') DEFAULT 'en_attente',
  `date_reservation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `reservations`
--

INSERT INTO `reservations` (`id`, `colis_id`, `voyage_id`, `statut`, `date_reservation`) VALUES
(26, 31, 12, 'accepte', '2025-07-29 12:47:32');

-- --------------------------------------------------------

--
-- Structure de la table `suivi_colis`
--

CREATE TABLE `suivi_colis` (
  `id` int(11) NOT NULL,
  `colis_id` int(11) NOT NULL,
  `statut` varchar(100) NOT NULL,
  `date_etape` datetime DEFAULT current_timestamp(),
  `confirme_par_admin` tinyint(1) DEFAULT 0,
  `demande_livraison` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `suivi_colis`
--

INSERT INTO `suivi_colis` (`id`, `colis_id`, `statut`, `date_etape`, `confirme_par_admin`, `demande_livraison`) VALUES
(25, 31, 'Livré', '2025-07-29 13:06:26', 1, 1),
(26, 31, 'Livré', '2025-07-30 12:40:11', 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `transporteurs`
--

CREATE TABLE `transporteurs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `numero_permis` varchar(100) DEFAULT NULL,
  `vehicule` varchar(100) DEFAULT NULL,
  `compagnie` varchar(100) DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `pays` varchar(100) DEFAULT NULL,
  `photo_vehicule` varchar(255) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `solde` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `transporteurs`
--

INSERT INTO `transporteurs` (`id`, `user_id`, `numero_permis`, `vehicule`, `compagnie`, `adresse`, `ville`, `pays`, `photo_vehicule`, `date_creation`, `solde`) VALUES
(4, 6, '1234567890', 'camion', 'v v bvb', ' nv nbvbnvbb', ' vjd fvhb', 'nv vh vh', 'uploads/vehicules/6888b47508395.jpg', '2025-07-29 12:45:58', 1920.00);

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `photo_profil` varchar(255) DEFAULT NULL,
  `date_inscription` datetime DEFAULT current_timestamp(),
  `role` enum('utilisateur','transporteur','admin') DEFAULT 'utilisateur',
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `prenom`, `email`, `password`, `telephone`, `photo_profil`, `date_inscription`, `role`, `reset_token`, `reset_expires`) VALUES
(1, 'YAYA', 'Malick', 'malick@gmail.com', '$2y$10$dRc8Zd2OUI65ZhRGr64WPOh5wuDAOOF4nieba/ZhnSe0GNr8ZYOdO', '0161571817', 'uploads/profil_1_1750681270.jpg', '2025-06-22 09:59:19', 'transporteur', NULL, NULL),
(3, 'SOUNOU', 'Elodie', 'elodie@gmail.com', '$2y$10$HIJpi1FOFm.Pz8tgysQTbeizaYGi.3BhLsgD9DGnSkvm502gHPHKO', '0141820082', 'uploads/profil_68598814f214c.jpg', '2025-06-23 19:00:05', 'transporteur', NULL, NULL),
(5, 'YAYA', 'Aicha', 'aicha@gmail.com', '$2y$10$HLXySx1qZE6JX3FK6Y0K2eWdXK/j8wstTO8lmhczj/M4Nq7lenhr6', '0196150607', 'uploads/profil_688119381933b.jpg', '2025-07-23 19:17:45', 'utilisateur', NULL, NULL),
(6, 'Elfred', 'DANGBENON', 'elfred434@gmail.com', '$2y$10$ujBk/uD32/dQ2OMKIzTzLu8fFV89CNc.lcwr.TeTYhAqjMpHZkPZ6', '0161256356', 'uploads/profil_6_1753782162.jpg', '2025-07-29 10:42:00', 'transporteur', NULL, NULL),
(7, 'bvnjvb', 'vnn nvnb', 'aicha5@gmail.com', '$2y$10$96ocYUoJs9UjuXpBC2s6v.cNU3Bx0pKYn30annwMR/YRBMwEhs8qu', '0190225577', 'Uploads/profil_6888b8963522e.png', '2025-07-29 13:03:34', 'utilisateur', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `voyages`
--

CREATE TABLE `voyages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `pays_depart` varchar(100) DEFAULT NULL,
  `pays_destination` varchar(100) DEFAULT NULL,
  `date_depart` date DEFAULT NULL,
  `heure_depart` time DEFAULT NULL,
  `poids_max` decimal(6,2) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `date_post` datetime DEFAULT current_timestamp(),
  `statut` enum('en_attente','approuve','refuse') DEFAULT 'en_attente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `voyages`
--

INSERT INTO `voyages` (`id`, `user_id`, `pays_depart`, `pays_destination`, `date_depart`, `heure_depart`, `poids_max`, `email`, `telephone`, `date_post`, `statut`) VALUES
(9, NULL, ' Hong Kong', 'Bénin', '2025-07-16', '17:00:00', 100.00, 'elfred434@gmail.com', '61256356', '2025-07-18 17:00:00', 'refuse'),
(11, NULL, ' Hong Kong', 'Bénin', '2025-07-16', '10:12:00', 100.00, 'elfred434@gmail.com', '61256356', '2025-07-20 11:53:33', 'approuve'),
(12, 6, 'Bénin', 'Bénin', '2020-12-25', '15:15:00', 9999.99, 'elfred434@gmail.com', '0161256356', '2025-07-29 12:45:57', 'en_attente');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `avis`
--
ALTER TABLE `avis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transporteur_id` (`transporteur_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `colis_id` (`colis_id`);

--
-- Index pour la table `colis`
--
ALTER TABLE `colis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_suivi` (`numero_suivi`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expediteur_id` (`expediteur_id`),
  ADD KEY `destinataire_id` (`destinataire_id`);

--
-- Index pour la table `messages_contact`
--
ALTER TABLE `messages_contact`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD UNIQUE KEY `numero_transaction` (`numero_transaction`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `colis_id` (`colis_id`);

--
-- Index pour la table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `colis_id` (`colis_id`),
  ADD KEY `voyage_id` (`voyage_id`);

--
-- Index pour la table `suivi_colis`
--
ALTER TABLE `suivi_colis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `colis_id` (`colis_id`);

--
-- Index pour la table `transporteurs`
--
ALTER TABLE `transporteurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `voyages`
--
ALTER TABLE `voyages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `avis`
--
ALTER TABLE `avis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `colis`
--
ALTER TABLE `colis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT pour la table `messages_contact`
--
ALTER TABLE `messages_contact`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT pour la table `suivi_colis`
--
ALTER TABLE `suivi_colis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT pour la table `transporteurs`
--
ALTER TABLE `transporteurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `voyages`
--
ALTER TABLE `voyages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `avis`
--
ALTER TABLE `avis`
  ADD CONSTRAINT `avis_ibfk_1` FOREIGN KEY (`transporteur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `avis_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `avis_ibfk_3` FOREIGN KEY (`colis_id`) REFERENCES `colis` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `colis`
--
ALTER TABLE `colis`
  ADD CONSTRAINT `colis_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`expediteur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`destinataire_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD CONSTRAINT `paiements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `paiements_ibfk_2` FOREIGN KEY (`colis_id`) REFERENCES `colis` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`colis_id`) REFERENCES `colis` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`voyage_id`) REFERENCES `voyages` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `suivi_colis`
--
ALTER TABLE `suivi_colis`
  ADD CONSTRAINT `suivi_colis_ibfk_1` FOREIGN KEY (`colis_id`) REFERENCES `colis` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `transporteurs`
--
ALTER TABLE `transporteurs`
  ADD CONSTRAINT `transporteurs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `voyages`
--
ALTER TABLE `voyages`
  ADD CONSTRAINT `voyages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
