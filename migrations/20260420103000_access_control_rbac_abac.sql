-- Athena RBAC + ABAC multi-tenant (idempotent)

ALTER TABLE `roles`
  ADD COLUMN IF NOT EXISTS `level` INT NOT NULL DEFAULT 0 AFTER `slug`;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned DEFAULT NULL,
  `code` varchar(190) NOT NULL,
  `slug` varchar(190) DEFAULT NULL,
  `label` varchar(190) DEFAULT NULL,
  `name` varchar(190) DEFAULT NULL,
  `category` varchar(64) NOT NULL DEFAULT 'module',
  `module` varchar(120) DEFAULT NULL,
  `action` varchar(32) DEFAULT NULL,
  `scope` varchar(32) DEFAULT NULL,
  `rbac_scope` varchar(32) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_permissions_tenant_code` (`tenant_id`,`code`),
  KEY `idx_permissions_tenant_category` (`tenant_id`,`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `permissions`
  ADD COLUMN IF NOT EXISTS `code` varchar(190) NULL AFTER `tenant_id`,
  ADD COLUMN IF NOT EXISTS `label` varchar(190) NULL AFTER `name`,
  ADD COLUMN IF NOT EXISTS `category` varchar(64) NOT NULL DEFAULT 'module' AFTER `label`;

UPDATE `permissions` SET `code` = `slug` WHERE (`code` IS NULL OR `code` = '') AND `slug` IS NOT NULL;
UPDATE `permissions` SET `label` = COALESCE(NULLIF(`name`, ''), `slug`, `code`) WHERE (`label` IS NULL OR `label` = '');

ALTER TABLE `permissions`
  MODIFY COLUMN `code` varchar(190) NOT NULL,
  ADD UNIQUE KEY IF NOT EXISTS `uniq_permissions_tenant_code` (`tenant_id`,`code`);

ALTER TABLE `role_permissions`
  ADD COLUMN IF NOT EXISTS `allowed` tinyint(1) NOT NULL DEFAULT 1 AFTER `permission_id`;

CREATE TABLE IF NOT EXISTS `access_rules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `name` varchar(190) NOT NULL,
  `description` text NULL,
  `target_type` varchar(16) NOT NULL,
  `target_id` int unsigned NOT NULL,
  `condition_type` varchar(32) NOT NULL,
  `condition_value` json NULL,
  `effect` varchar(8) NOT NULL,
  `priority` int NOT NULL DEFAULT 100,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_access_rules_tenant_target` (`tenant_id`,`target_type`,`target_id`,`is_active`),
  KEY `idx_access_rules_priority` (`tenant_id`,`priority`,`effect`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `access_scopes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `rule_id` int unsigned NOT NULL,
  `scope_type` varchar(16) NOT NULL,
  `scope_identifier` varchar(190) NOT NULL,
  `action` varchar(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_access_scopes_rule` (`rule_id`),
  KEY `idx_access_scopes_lookup` (`scope_type`,`scope_identifier`,`action`),
  CONSTRAINT `fk_access_scopes_rule` FOREIGN KEY (`rule_id`) REFERENCES `access_rules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `access_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `resource` varchar(190) NOT NULL,
  `action` varchar(16) NOT NULL,
  `decision` varchar(8) NOT NULL,
  `reason` varchar(255) NULL,
  `context_json` json NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_access_logs_actor` (`tenant_id`,`user_id`,`created_at`),
  KEY `idx_access_logs_resource` (`resource`,`action`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeds permissions (idempotent)
INSERT INTO permissions (`tenant_id`,`code`,`slug`,`label`,`name`,`category`,`module`,`action`,`scope`,`rbac_scope`)
SELECT NULL, 'documents.read', 'documents.read', 'Lire documents', 'Lire documents', 'module', 'documents', 'READ', 'tenant', 'tenant'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE tenant_id IS NULL AND code = 'documents.read');
INSERT INTO permissions (`tenant_id`,`code`,`slug`,`label`,`name`,`category`,`module`,`action`,`scope`,`rbac_scope`)
SELECT NULL, 'documents.export', 'documents.export', 'Exporter documents', 'Exporter documents', 'action', 'documents', 'EXPORT', 'tenant', 'tenant'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE tenant_id IS NULL AND code = 'documents.export');
INSERT INTO permissions (`tenant_id`,`code`,`slug`,`label`,`name`,`category`,`module`,`action`,`scope`,`rbac_scope`)
SELECT NULL, 'courrier.read', 'courrier.read', 'Lire courriers', 'Lire courriers', 'module', 'courrier', 'READ', 'tenant', 'tenant'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE tenant_id IS NULL AND code = 'courrier.read');
INSERT INTO permissions (`tenant_id`,`code`,`slug`,`label`,`name`,`category`,`module`,`action`,`scope`,`rbac_scope`)
SELECT NULL, 'admin.access.manage', 'admin.access.manage', 'Gérer les accès', 'Gérer les accès', 'page', 'admin', 'WRITE', 'tenant', 'tenant'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE tenant_id IS NULL AND code = 'admin.access.manage');

-- Seeds ABAC templates (role target by slug lookup)
INSERT INTO access_rules (`tenant_id`,`name`,`description`,`target_type`,`target_id`,`condition_type`,`condition_value`,`effect`,`priority`,`is_active`)
SELECT r.tenant_id, 'Recrue - accès progressif documents', 'Débloque lecture après 7 jours', 'ROLE', r.id, 'DAYS_SINCE_CREATION', JSON_OBJECT('days',7), 'ALLOW', 120, 1
FROM roles r
WHERE r.slug = 'recrue'
  AND NOT EXISTS (SELECT 1 FROM access_rules ar WHERE ar.tenant_id = r.tenant_id AND ar.name = 'Recrue - accès progressif documents');

INSERT INTO access_scopes (`rule_id`,`scope_type`,`scope_identifier`,`action`)
SELECT ar.id, 'MODULE', 'documents', 'READ'
FROM access_rules ar
WHERE ar.name = 'Recrue - accès progressif documents'
  AND NOT EXISTS (SELECT 1 FROM access_scopes s WHERE s.rule_id = ar.id AND s.scope_type = 'MODULE' AND s.scope_identifier = 'documents' AND s.action = 'READ');
