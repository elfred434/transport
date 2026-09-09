-- Migration 001 : table messages_admin (messagerie utilisateur <-> admin)
-- + création d'un compte administrateur initial.
-- À exécuter sur la base transport_db.

CREATE TABLE IF NOT EXISTS `messages_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expediteur_id` int(11) NOT NULL,
  `destinataire_id` int(11) NOT NULL,
  `contenu` text NOT NULL,
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  `date_envoi` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `expediteur_id` (`expediteur_id`),
  KEY `destinataire_id` (`destinataire_id`),
  CONSTRAINT `messages_admin_ibfk_1` FOREIGN KEY (`expediteur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_admin_ibfk_2` FOREIGN KEY (`destinataire_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compte administrateur initial : admin@transport.bj / Admin@12345
-- >>> CHANGEZ CE MOT DE PASSE après la première connexion <<<
INSERT INTO `users` (`nom`, `prenom`, `email`, `password`, `telephone`, `role`)
SELECT 'ADMIN', 'Super', 'admin@transport.bj',
       '$2y$12$kGitM5Y9Wz9xk1XRbBwDnuGFV4vgA9v6ZH9D3uD7NfgvYPDmUnekW', NULL, 'admin'
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'admin@transport.bj');
