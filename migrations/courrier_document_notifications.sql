-- Notifications courrier (lecture document signalée aux membres du tenant)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `courrier_document_notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `document_id` int unsigned NOT NULL,
  `recipient_user_id` int unsigned NOT NULL,
  `created_by_user_id` int unsigned NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doc_recipient` (`document_id`,`recipient_user_id`),
  KEY `idx_tenant_recipient_unread` (`tenant_id`,`recipient_user_id`,`read_at`),
  KEY `idx_document` (`document_id`),
  CONSTRAINT `cdn_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cdn_doc_fk` FOREIGN KEY (`document_id`) REFERENCES `courrier_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cdn_recipient_fk` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cdn_creator_fk` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
