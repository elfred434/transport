-- Migration 002 : hiérarchie des rôles Super admin / Admin / Transporteur / Client
-- Met à jour l'ENUM users.role pour inclure super_admin et client (alias de utilisateur)

SET FOREIGN_KEY_CHECKS=0;

-- Elargir l'ENUM pour inclure les nouveaux rôles tout en gardant l'ancien 'utilisateur' pour compatibilité
ALTER TABLE `users` MODIFY `role` ENUM('client','utilisateur','transporteur','admin','super_admin') NOT NULL DEFAULT 'client';

-- Migrer les anciens 'utilisateur' vers 'client'
UPDATE `users` SET `role`='client' WHERE `role`='utilisateur';

-- Promouvoir l'admin initial en super_admin
UPDATE `users` SET `role`='super_admin' WHERE `email`='admin@transport.bj';

SET FOREIGN_KEY_CHECKS=1;
