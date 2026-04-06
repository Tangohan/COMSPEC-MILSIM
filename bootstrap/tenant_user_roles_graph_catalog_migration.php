<?php

declare(strict_types=1);

/**
 * RBAC « toile » : pivot tenant_user_roles, graphe role_relations, catalogue role_definitions,
 * overrides, rbac_scope sur permissions, extensions badges / certifications / clearance.
 * Idempotent — appelée depuis run-migrations.php.
 */
function run_tenant_user_roles_graph_catalog_migration(PDO $pdo): void
{
    // --- role_definitions (catalogue global) ---
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `role_definitions` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `slug` varchar(100) NOT NULL,
            `name_fr` varchar(160) NOT NULL,
            `name_us` varchar(160) NOT NULL,
            `family` varchar(64) NOT NULL DEFAULT \'general\',
            `description` varchar(600) DEFAULT NULL,
            `sort_order` int NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_role_definitions_slug` (`slug`),
            KEY `idx_role_definitions_family` (`family`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `role_definition_relations` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `from_definition_id` int unsigned NOT NULL,
            `to_definition_id` int unsigned NOT NULL,
            `relation_type` varchar(32) NOT NULL DEFAULT \'reports_to\',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_rdr_pair` (`from_definition_id`,`to_definition_id`,`relation_type`),
            KEY `idx_rdr_to` (`to_definition_id`),
            CONSTRAINT `rdr_from_fk` FOREIGN KEY (`from_definition_id`) REFERENCES `role_definitions` (`id`) ON DELETE CASCADE,
            CONSTRAINT `rdr_to_fk` FOREIGN KEY (`to_definition_id`) REFERENCES `role_definitions` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $chk = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'definition_id' LIMIT 1");
    if ($chk && !$chk->fetch()) {
        $pdo->exec('ALTER TABLE `roles` ADD COLUMN `definition_id` int unsigned DEFAULT NULL AFTER `role_layer`');
        $pdo->exec('ALTER TABLE `roles` ADD KEY `roles_definition_id` (`definition_id`)');
        try {
            $pdo->exec('ALTER TABLE `roles` ADD CONSTRAINT `roles_definition_fk` FOREIGN KEY (`definition_id`) REFERENCES `role_definitions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (PDOException) {
        }
    }

    // --- permissions.rbac_scope ---
    $chkP = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permissions' AND COLUMN_NAME = 'rbac_scope' LIMIT 1");
    if ($chkP && !$chkP->fetch()) {
        $pdo->exec("ALTER TABLE `permissions` ADD COLUMN `rbac_scope` enum('global','tenant','unit') NOT NULL DEFAULT 'tenant' AFTER `scope`");
        $pdo->exec("UPDATE `permissions` SET `rbac_scope` = CASE `scope` WHEN 'site' THEN 'global' WHEN 'community' THEN 'tenant' ELSE 'unit' END");
    }

    // --- tenant_user_roles ---
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `tenant_user_roles` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tenant_id` int unsigned NOT NULL,
            `user_id` int unsigned NOT NULL,
            `role_id` int unsigned NOT NULL,
            `org_unit_id` int unsigned DEFAULT NULL COMMENT \'NULL = rôle tenant (hors périmètre unitaire)\',
            `valid_from` datetime DEFAULT NULL,
            `valid_until` datetime DEFAULT NULL,
            `metadata` json DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `co_unit_id` bigint unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_tur_scope` (`tenant_id`,`user_id`,`role_id`,`co_unit_id`),
            KEY `idx_tur_user` (`user_id`),
            KEY `idx_tur_tenant_role` (`tenant_id`,`role_id`),
            KEY `idx_tur_unit` (`org_unit_id`),
            CONSTRAINT `tur_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `tur_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `tur_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `tur_unit_fk` FOREIGN KEY (`org_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // --- role_relations (graphe par tenant) ---
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `role_relations` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tenant_id` int unsigned NOT NULL,
            `from_role_id` int unsigned NOT NULL,
            `to_role_id` int unsigned NOT NULL,
            `relation_type` varchar(32) NOT NULL DEFAULT \'reports_to\',
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_rr_tenant_pair` (`tenant_id`,`from_role_id`,`to_role_id`,`relation_type`),
            KEY `idx_rr_from` (`from_role_id`),
            KEY `idx_rr_to` (`to_role_id`),
            CONSTRAINT `rr_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `rr_from_fk` FOREIGN KEY (`from_role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
            CONSTRAINT `rr_to_fk` FOREIGN KEY (`to_role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // --- user_permission_overrides ---
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `user_permission_overrides` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tenant_id` int unsigned NOT NULL,
            `user_id` int unsigned NOT NULL,
            `permission_id` int unsigned NOT NULL,
            `grant_flag` tinyint(1) NOT NULL DEFAULT 1 COMMENT \'1=accorder 0=révoquer\',
            `org_unit_id` int unsigned DEFAULT NULL,
            `co_unit_scope` bigint unsigned NOT NULL DEFAULT 0,
            `reason` varchar(255) DEFAULT NULL,
            `created_by_user_id` int unsigned DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_upo` (`tenant_id`,`user_id`,`permission_id`,`co_unit_scope`),
            KEY `idx_upo_user` (`user_id`),
            CONSTRAINT `upo_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
            CONSTRAINT `upo_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `upo_perm_fk` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
            CONSTRAINT `upo_unit_fk` FOREIGN KEY (`org_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // --- Extensions : badges, certifications, clearance_levels ---
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `badges` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tenant_id` int unsigned NOT NULL,
            `slug` varchar(80) NOT NULL,
            `name` varchar(160) NOT NULL,
            `description` varchar(500) DEFAULT NULL,
            `icon_url` varchar(500) DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_badges_tenant_slug` (`tenant_id`,`slug`),
            CONSTRAINT `badges_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `user_badges` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tenant_id` int unsigned NOT NULL,
            `user_id` int unsigned NOT NULL,
            `badge_id` int unsigned NOT NULL,
            `granted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `granted_by_user_id` int unsigned DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_user_badge` (`user_id`,`badge_id`),
            KEY `idx_ub_tenant` (`tenant_id`),
            CONSTRAINT `ub_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
            CONSTRAINT `ub_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `ub_badge_fk` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `certifications` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tenant_id` int unsigned NOT NULL,
            `slug` varchar(80) NOT NULL,
            `name` varchar(160) NOT NULL,
            `description` varchar(600) DEFAULT NULL,
            `training_course_id` int unsigned DEFAULT NULL,
            `validity_days` int unsigned DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_cert_tenant_slug` (`tenant_id`,`slug`),
            KEY `idx_cert_course` (`training_course_id`),
            CONSTRAINT `cert_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `user_certifications` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tenant_id` int unsigned NOT NULL,
            `user_id` int unsigned NOT NULL,
            `certification_id` int unsigned NOT NULL,
            `training_course_id` int unsigned DEFAULT NULL,
            `status` varchar(32) NOT NULL DEFAULT \'active\',
            `issued_at` datetime DEFAULT NULL,
            `expires_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_ucert_user` (`user_id`,`certification_id`),
            KEY `idx_ucert_tenant` (`tenant_id`),
            CONSTRAINT `ucert_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
            CONSTRAINT `ucert_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `ucert_cert_fk` FOREIGN KEY (`certification_id`) REFERENCES `certifications` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `clearance_levels` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tenant_id` int unsigned NOT NULL,
            `slug` varchar(80) NOT NULL,
            `name` varchar(120) NOT NULL,
            `rank_order` int NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_clearance_tenant_slug` (`tenant_id`,`slug`),
            CONSTRAINT `clr_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $chkCl = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_extras' AND COLUMN_NAME = 'clearance_level_id' LIMIT 1");
    if ($chkCl && !$chkCl->fetch()) {
        try {
            $pdo->exec('ALTER TABLE `personnel_extras` ADD COLUMN `clearance_level_id` int unsigned DEFAULT NULL AFTER `clearance_level`');
            $pdo->exec('ALTER TABLE `personnel_extras` ADD KEY `personnel_extras_clearance_level_id` (`clearance_level_id`)');
            $pdo->exec('ALTER TABLE `personnel_extras` ADD CONSTRAINT `pe_clearance_fk` FOREIGN KEY (`clearance_level_id`) REFERENCES `clearance_levels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (PDOException) {
        }
    }

    // Backfill tenant_user_roles depuis user_roles
    $turExists = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_user_roles' LIMIT 1");
    $urExists = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_roles' LIMIT 1");
    if ($turExists && $turExists->fetch() && $urExists && $urExists->fetch()) {
        try {
            $pdo->exec(
                'INSERT IGNORE INTO tenant_user_roles (tenant_id, user_id, role_id, org_unit_id, co_unit_id, created_at)
                 SELECT u.tenant_id, ur.user_id, ur.role_id, NULL, 0, COALESCE(ur.created_at, NOW())
                 FROM user_roles ur
                 INNER JOIN users u ON u.id = ur.user_id'
            );
        } catch (PDOException) {
        }
    }

    require_once __DIR__ . '/../app/Services/Rbac/RoleDefinitionCatalog.php';
    try {
        \App\Services\Rbac\RoleDefinitionCatalog::seed($pdo);
    } catch (Throwable $e) {
        echo 'RBAC catalogue seed : ' . $e->getMessage() . "\n";
    }

    echo "RBAC toile : tenant_user_roles, role_relations, role_definitions, extensions OK.\n";
}
