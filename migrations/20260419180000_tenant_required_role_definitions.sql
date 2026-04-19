-- Fonctions doctrinales marquées obligatoires par communauté (couverture S1 / RBAC)
CREATE TABLE IF NOT EXISTS `tenant_required_role_definitions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `role_definition_id` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_trrd_tenant_definition` (`tenant_id`,`role_definition_id`),
  KEY `idx_trrd_tenant` (`tenant_id`),
  CONSTRAINT `trrd_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `trrd_definition_fk` FOREIGN KEY (`role_definition_id`) REFERENCES `role_definitions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
