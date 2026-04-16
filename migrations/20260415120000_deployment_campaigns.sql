-- Campagnes de publication (file ordonnée) + enrichissement deployment_jobs.
-- Exécution recommandée via run-migrations.php (bloc conditionnel information_schema).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `deployment_campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint unsigned NOT NULL,
  `module_version_id` bigint unsigned NOT NULL,
  `triggered_by` int unsigned DEFAULT NULL,
  `status` enum('queued','in_progress','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_deployment_campaigns_status` (`status`,`created_at`),
  KEY `idx_deployment_campaigns_module` (`module_id`),
  CONSTRAINT `fk_deployment_campaigns_module` FOREIGN KEY (`module_id`) REFERENCES `platform_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_deployment_campaigns_version` FOREIGN KEY (`module_version_id`) REFERENCES `platform_module_versions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_deployment_campaigns_actor` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
