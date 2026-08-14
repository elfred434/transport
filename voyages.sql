-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mer. 16 juil. 2025 à 21:56
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
  `statut` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `voyages`
--

INSERT INTO `voyages` (`id`, `user_id`, `pays_depart`, `pays_destination`, `date_depart`, `heure_depart`, `poids_max`, `email`, `telephone`, `date_post`, `statut`) VALUES
(1, 1, 'Bénin', 'japon ', '2025-06-22', '12:22:00', 20.00, 'elfred434@gmail.com', '0141820082', '2025-06-22 10:18:30', 0),
(2, 2, 'Bénin', 'Japon', '2025-06-23', '12:00:00', 7.00, 'elfred434@gmail.com', '61256356', '2025-06-22 14:42:47', 0),
(3, 1, 'Bénin', 'japon', '2025-06-22', '22:02:00', 50.00, 'malick@gmail.com', '0161571817', '2025-06-22 17:22:14', 0),
(4, 2, 'Bénin', 'Japon', '2025-06-23', '14:58:00', 7.00, 'elfred434@gmail.com', '61256356', '2025-07-01 13:58:42', 0),
(5, 2, 'Bénin', 'Etats Unis', '2025-06-23', '12:51:00', 7.00, 'elfred434@gmail.com', '61256356', '2025-07-03 12:51:16', 0),
(6, 2, 'Bénin', 'Bénin', '2025-07-31', '13:01:00', 50.00, 'elfred434@gmail.com', '61256356', '2025-07-03 13:01:47', 0);

--
-- Index pour les tables déchargées
--

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
-- AUTO_INCREMENT pour la table `voyages`
--
ALTER TABLE `voyages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `voyages`
--
ALTER TABLE `voyages`
  ADD CONSTRAINT `voyages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
