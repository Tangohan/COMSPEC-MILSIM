SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `hr_charter_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `title` varchar(190) NOT NULL,
  `content_markdown` mediumtext NOT NULL,
  `effective_at` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_by_user_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_charter_versions_code` (`code`),
  KEY `idx_hr_charter_versions_active` (`is_active`,`effective_at`),
  CONSTRAINT `fk_hr_charter_versions_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_charter_acceptances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `charter_version_id` bigint unsigned NOT NULL,
  `accepted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_charter_acceptances_user_version` (`tenant_id`,`user_id`,`charter_version_id`),
  KEY `idx_hr_charter_acceptances_tenant_accepted` (`tenant_id`,`accepted_at`),
  CONSTRAINT `fk_hr_charter_acceptances_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_charter_acceptances_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hr_charter_acceptances_version` FOREIGN KEY (`charter_version_id`) REFERENCES `hr_charter_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_lms_tracks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `slug` varchar(80) NOT NULL,
  `name` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hr_lms_tracks_tenant_slug` (`tenant_id`,`slug`),
  CONSTRAINT `fk_hr_lms_tracks_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `hr_charter_versions` (`code`,`title`,`content_markdown`,`effective_at`,`is_active`)
SELECT 'AGORHA-CHARTE-2026-01',
       'Charte d’utilisation SIRH/LMS — version 2026.1',
       '### Principes clés\n\n1. Besoin d’en connaître.\n2. Usage strictement professionnel.\n3. Traçabilité complète des accès.\n4. Interdiction des extractions frauduleuses.\n5. Signalement immédiat des incidents.',
       NOW(),
       1
WHERE NOT EXISTS (SELECT 1 FROM `hr_charter_versions` WHERE `code` = 'AGORHA-CHARTE-2026-01');
