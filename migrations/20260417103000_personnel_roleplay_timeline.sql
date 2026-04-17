SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `personnel_roleplay_timeline_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `event_type` varchar(60) NOT NULL,
  `title` varchar(180) NOT NULL,
  `detail` text DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('planned','completed','blocked','cancelled') NOT NULL DEFAULT 'planned',
  `progress_delta` smallint DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `prte_tenant_user_idx` (`tenant_id`,`user_id`,`event_date`),
  KEY `prte_due_idx` (`tenant_id`,`due_date`,`status`),
  CONSTRAINT `prte_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `prte_actor_fk` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `prte_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
