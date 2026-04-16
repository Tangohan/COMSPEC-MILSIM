-- Fil d’activité pour le tableau de bord organisationnel + cooldown alertes module
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tenant_community_feed` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `category` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT,
  `link_url` VARCHAR(512) DEFAULT NULL,
  `actor_user_id` INT UNSIGNED DEFAULT NULL,
  `related_enrollment_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tcf_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_tcf_related_enrollment` (`tenant_id`,`related_enrollment_id`),
  CONSTRAINT `tcf_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `training_staff_ping_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `enrollment_id` BIGINT UNSIGNED NOT NULL,
  `module_id` BIGINT UNSIGNED NOT NULL,
  `ping_kind` VARCHAR(32) NOT NULL DEFAULT 'module_blocked',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tsp_cooldown` (`enrollment_id`,`module_id`,`ping_kind`,`created_at`),
  CONSTRAINT `tsp_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
