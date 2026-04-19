-- Volontariat recruteurs + bilans à ~1 mois (équipe + candidat)
-- Idempotent : exécuter via le script de migration du projet

CREATE TABLE IF NOT EXISTS `enlistment_recruiter_picks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `enlistment_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pick` (`tenant_id`, `enlistment_id`, `user_id`),
  KEY `idx_enlist` (`tenant_id`, `enlistment_id`),
  CONSTRAINT `erp_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `erp_enlist_fk` FOREIGN KEY (`enlistment_id`) REFERENCES `enlistments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `erp_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `enlistment_retro_feedbacks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `enlistment_id` int unsigned NOT NULL,
  `feedback_scope` varchar(32) NOT NULL,
  `author_user_id` int unsigned DEFAULT NULL,
  `rating` tinyint unsigned DEFAULT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_retro_scope` (`tenant_id`, `enlistment_id`, `feedback_scope`),
  KEY `idx_retro_enlist` (`tenant_id`, `enlistment_id`),
  CONSTRAINT `erf_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `erf_enlist_fk` FOREIGN KEY (`enlistment_id`) REFERENCES `enlistments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `erf_author_fk` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
