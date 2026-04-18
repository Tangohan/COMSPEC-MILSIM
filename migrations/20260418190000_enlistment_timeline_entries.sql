-- Journal des dossiers de recrutement : événements système et notes du personnel par étape.

CREATE TABLE IF NOT EXISTS `enlistment_timeline_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `enlistment_id` int unsigned NOT NULL,
  `entry_kind` varchar(20) NOT NULL COMMENT 'system | staff_note',
  `step_code` varchar(40) NOT NULL DEFAULT 'general',
  `summary` varchar(500) DEFAULT NULL,
  `body` text,
  `actor_user_id` int unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_enlist_created` (`tenant_id`, `enlistment_id`, `created_at`),
  CONSTRAINT `enlistment_timeline_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `enlistment_timeline_enlistment_fk` FOREIGN KEY (`enlistment_id`) REFERENCES `enlistments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `enlistment_timeline_actor_fk` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
