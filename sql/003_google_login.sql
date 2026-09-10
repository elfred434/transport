-- Migration 003 : Google Login + élargissement rôles
-- Ajout colonnes google_id, provider, avatar externe, et élargissement enum role

-- Elargir role pour inclure client et super_admin (si pas déjà fait)
ALTER TABLE `users` MODIFY COLUMN `role` ENUM('utilisateur','client','transporteur','admin','super_admin') DEFAULT 'utilisateur';

-- Ajouter colonnes Google
ALTER TABLE `users` 
  ADD COLUMN `google_id` VARCHAR(255) NULL AFTER `photo_profil`,
  ADD COLUMN `provider` VARCHAR(50) NULL DEFAULT NULL AFTER `google_id`,
  ADD COLUMN `avatar` VARCHAR(500) NULL AFTER `provider`,
  ADD UNIQUE KEY `google_id_unique` (`google_id`);

-- Optionnel : rendre password nullable déjà OK, mais s'assurer
-- ALTER TABLE `users` MODIFY COLUMN `password` VARCHAR(255) NULL;
