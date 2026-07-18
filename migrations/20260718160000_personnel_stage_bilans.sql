-- Bilans d’étape sur la fiche personnel (idempotent via bootstrap)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `personnel_stage_bilans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `bilan_kind` enum('recrutement','rh','commandement') NOT NULL DEFAULT 'rh',
  `stage_label` varchar(120) NOT NULL,
  `title` varchar(180) NOT NULL,
  `rating` tinyint unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `event_date` date NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_psb_tenant_user_date` (`tenant_id`,`user_id`,`event_date`),
  KEY `idx_psb_kind` (`tenant_id`,`bilan_kind`),
  CONSTRAINT `fk_psb_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_psb_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_psb_author` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
