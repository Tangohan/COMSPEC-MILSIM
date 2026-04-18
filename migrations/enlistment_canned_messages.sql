-- Messages préfaits pour le commentaire interne (décision candidature), par tenant.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `enlistment_canned_messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `label` varchar(160) NOT NULL,
  `body` text NOT NULL,
  `context` varchar(32) NOT NULL DEFAULT 'generic',
  `sort_order` int unsigned NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_sort` (`tenant_id`, `sort_order`),
  KEY `tenant_context_sort` (`tenant_id`, `context`, `sort_order`),
  CONSTRAINT `enlistment_canned_messages_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
