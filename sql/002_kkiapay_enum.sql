-- Ajout de la méthode kkiapay à l'enum methode_paiement pour intégration 100% Kkiapay
-- Exécuter sur la base transport_db après 000_schema_base.sql
ALTER TABLE `paiements` MODIFY COLUMN `methode_paiement` ENUM('mobile_money','carte_credit','virement','autre','kkiapay') DEFAULT NULL;
