-- 005_notifications_signal.sql
-- Table de notifications admin pour signaler les livraisons à confirmer
-- et futurs événements système.

CREATE TABLE IF NOT EXISTS `notifications_admin` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(50) NOT NULL DEFAULT 'livraison',
  `colis_id` BIGINT UNSIGNED NULL,
  `suivi_id` BIGINT UNSIGNED NULL,
  `transporteur_id` BIGINT UNSIGNED NULL,
  `message` TEXT NOT NULL,
  `lu` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  INDEX idx_notif_lu (`lu`),
  INDEX idx_notif_type (`type`),
  INDEX idx_notif_colis (`colis_id`),
  INDEX idx_notif_created (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
