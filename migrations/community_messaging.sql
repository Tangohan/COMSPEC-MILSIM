-- Messagerie interne par communauté (tenant)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tenant_message_threads` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `subject` varchar(255) NOT NULL DEFAULT '',
  `created_by_user_id` int unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_updated` (`tenant_id`,`updated_at`),
  CONSTRAINT `tmt_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tmt_creator_fk` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tenant_message_thread_users` (
  `thread_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `last_read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`thread_id`,`user_id`),
  KEY `user_tenant_lookup` (`user_id`),
  CONSTRAINT `tmtu_thread_fk` FOREIGN KEY (`thread_id`) REFERENCES `tenant_message_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tmtu_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tenant_messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` int unsigned NOT NULL,
  `sender_user_id` int unsigned NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `thread_created` (`thread_id`,`created_at`),
  CONSTRAINT `tm_thread_fk` FOREIGN KEY (`thread_id`) REFERENCES `tenant_message_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tm_sender_fk` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
