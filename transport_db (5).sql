-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 25 juil. 2025 à 15:59
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

--
-- Déchargement des données de la table `avis`
--

INSERT INTO `avis` (`id`, `transporteur_id`, `user_id`, `colis_id`, `note`, `commentaire`, `date_avis`, `statut`) VALUES
(2, 2, 3, 17, 5, 't\'resbien', '2025-07-20 14:02:39', 'approuve');

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
(14, 2, 'infin', 'uploads/colis_687b444cba784.jpeg', 'documents', 50, 50.00, '6 7 8', 'Bénin', 'Porto novo', '5555-05-05', 'cotonou', 'porto novo', 51000.00, '2025-07-19 09:07:56', 'approuve', 'COLIS687b444cc819d'),
(16, 2, 'infinixx', 'uploads/colis_687e6dfe0a7d8.jpeg', 'vetements', 50, 100.00, '6 7 8', 'Bénin', 'Porto novo', '5555-05-05', 'cotonou', 'porto novo', 101000.00, '2025-07-19 14:32:18', 'approuve', 'COLIS687b9052880d0'),
(17, 3, 'ismael', 'uploads/colis_687cc8a43380a.webp', 'documents', 10, 10.00, '5 5 5', 'benin', 'porto novo', '2025-02-05', 'porto', 'porto', 11000.00, '2025-07-20 12:44:52', 'approuve', 'COLIS687cc8a460f50'),
(18, 3, 'Drogue', 'uploads/colis_687d3921c0bcc.jpg', 'vetements', 10, 10.00, '5 5 5', 'benin', 'cotonou', '2025-02-05', 'porto', 'cotonou', 11000.00, '2025-07-20 20:44:49', 'approuve', 'COLIS687d3921c1f77'),
(26, 2, 'Techno pop 10', 'uploads/colis_687e93d91bcaa.jpeg', 'documents', 10, 1.00, '555', 'Bénin', 'Porto novo', '5252-02-25', 'cotonou', 'porto novo', 2000.00, '2025-07-21 21:24:09', 'approuve', 'COLIS687e93d91c40f');

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
(2, 1, 2, 1, 2, 'salut', '2025-06-22 14:44:07', 0, NULL),
(3, 1, 2, 1, 2, 'bien', '2025-06-22 14:44:34', 0, NULL),
(4, 2, 1, 1, 2, 'ok', '2025-06-23 16:28:28', 0, NULL),
(5, 2, 1, 1, 2, 'salut', '2025-07-01 13:26:06', 0, NULL),
(11, 2, 1, 1, 2, 's', '2025-07-08 00:24:12', 0, NULL),
(12, 2, 1, NULL, NULL, 's', '2025-07-08 00:31:44', 0, NULL),
(13, 2, 1, NULL, NULL, '[Fichier joint: 231943.jpg]', '2025-07-08 00:43:34', 0, 'uploads/messages/msg_686c4d95d10a7.jpg'),
(14, 2, 1, NULL, NULL, 'bonjour', '2025-07-08 12:24:22', 0, NULL),
(15, 2, 1, NULL, NULL, '[Fichier joint: 231951.jpg]', '2025-07-08 12:27:09', 0, 'uploads/messages/msg_686cf27cf20c0.jpg'),
(16, 2, 1, NULL, NULL, 'slt toca\r\n\r\nrd', '2025-07-11 14:16:17', 0, NULL),
(17, 2, 1, NULL, NULL, 'hghj', '2025-07-18 13:34:25', 0, NULL),
(18, 2, 1, NULL, NULL, 'b', '2025-07-18 19:21:12', 0, NULL),
(19, 3, 3, NULL, NULL, 'salut', '2025-07-20 14:14:13', 0, NULL),
(20, 2, 3, NULL, NULL, 'sl', '2025-07-20 14:15:06', 0, NULL),
(21, 3, 2, NULL, NULL, 'bonjour je veux discutez avec vous', '2025-07-20 14:18:02', 0, NULL),
(22, 2, 3, NULL, NULL, 'ff', '2025-07-20 14:31:49', 0, NULL),
(23, 3, 2, NULL, NULL, 'colis id 18', '2025-07-20 20:46:36', 0, NULL),
(24, 2, 3, NULL, NULL, 'ok réceptionné', '2025-07-20 20:47:11', 0, NULL),
(25, 2, 1, NULL, NULL, 'salut', '2025-07-20 23:09:05', 0, NULL);

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
(15, 14, 9, 'accepte', '2025-07-19 09:08:25'),
(16, 16, 9, 'accepte', '2025-07-20 10:56:26'),
(18, 16, 11, 'refuse', '2025-07-20 11:53:46'),
(19, 17, 11, 'accepte', '2025-07-20 12:45:37'),
(20, 18, 11, 'accepte', '2025-07-20 20:45:47'),
(25, 26, 11, 'accepte', '2025-07-24 20:09:27');

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
(12, 14, 'En cours', '2025-07-19 09:13:41', 1, 0),
(13, 14, 'Livré', '2025-07-19 09:14:00', 1, 1),
(17, 17, 'Livré', '2025-07-20 14:15:42', 1, 1),
(18, 18, 'Livré', '2025-07-20 20:47:41', 1, 1),
(24, 26, 'Livré', '2025-07-24 20:09:47', 1, 1);

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
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `transporteurs`
--

INSERT INTO `transporteurs` (`id`, `user_id`, `numero_permis`, `vehicule`, `compagnie`, `adresse`, `ville`, `pays`, `photo_vehicule`, `date_creation`) VALUES
(3, 2, '1234567890', ': Camion 20m³', 'TransExpress', 'cotonou', 'Porto novo', 'Bénin', NULL, '2025-07-18 17:00:00');

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
(2, 'Elfred', 'DANGBENON', 'elfred434@gmail.com', '$2y$10$fUcCZCU2lVd5ZceOwJnSTuc/B8vbFaPv5UJY3YeBvRCk5fmmraT/W', '+229 0161256356', 'uploads/profil_2_1753023188.jpg', '2025-06-22 10:52:50', 'transporteur', NULL, NULL),
(3, 'SOUNOU', 'Elodie', 'elodie@gmail.com', '$2y$10$HIJpi1FOFm.Pz8tgysQTbeizaYGi.3BhLsgD9DGnSkvm502gHPHKO', '0141820082', 'uploads/profil_68598814f214c.jpg', '2025-06-23 19:00:05', 'utilisateur', NULL, NULL),
(5, 'YAYA', 'Aicha', 'aicha@gmail.com', '$2y$10$HLXySx1qZE6JX3FK6Y0K2eWdXK/j8wstTO8lmhczj/M4Nq7lenhr6', '0196150607', 'uploads/profil_688119381933b.jpg', '2025-07-23 19:17:45', 'utilisateur', NULL, NULL);

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
(9, 2, ' Hong Kong', 'Bénin', '2025-07-16', '17:00:00', 100.00, 'elfred434@gmail.com', '61256356', '2025-07-18 17:00:00', 'refuse'),
(11, 2, ' Hong Kong', 'Bénin', '2025-07-16', '10:12:00', 100.00, 'elfred434@gmail.com', '61256356', '2025-07-20 11:53:33', 'approuve');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT pour la table `messages_contact`
--
ALTER TABLE `messages_contact`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT pour la table `suivi_colis`
--
ALTER TABLE `suivi_colis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT pour la table `transporteurs`
--
ALTER TABLE `transporteurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `voyages`
--
ALTER TABLE `voyages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
