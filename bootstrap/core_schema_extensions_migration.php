<?php

declare(strict_types=1);

/**
 * Extensions de schéma hors schema.sql : tableau opérationnel, ORBAT, e-mails tenant, maintenance.
 * Idempotent (CREATE IF NOT EXISTS / colonnes vérifiées via information_schema).
 * Anciennement assuré par l’outil Phinx (retiré du projet).
 */

/**
 * @param callable():void $flush
 */
function run_core_schema_extensions_migration(PDO $pdo, string $root, callable $flush): void
{
    echo "Migrations DDL étendues (tableau opérationnel, ORBAT, e-mail tenant…)…\n";
    $flush();

    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $execTry = static function (PDO $pdo, string $sql, string $label) use ($flush): void {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            echo '  [ATTENTION] ' . $label . ' : ' . $e->getMessage() . "\n";
            $flush();
        }
    };

    // --- Utilisateurs : identifiant Athena (9 caractères, unique) ---
    if ($tableExists($pdo, 'users')) {
        if (!$columnExists($pdo, 'users', 'athena_identifier')) {
            $execTry(
                $pdo,
                "ALTER TABLE users ADD COLUMN athena_identifier CHAR(9) NULL AFTER profile_slug",
                'users.athena_identifier'
            );
        }
        try {
            $idxStmt = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'users_athena_identifier_unique' LIMIT 1");
            $hasIdx = $idxStmt && (bool) $idxStmt->fetchColumn();
            if (!$hasIdx && $columnExists($pdo, 'users', 'athena_identifier')) {
                $execTry(
                    $pdo,
                    "ALTER TABLE users ADD UNIQUE KEY users_athena_identifier_unique (athena_identifier)",
                    'users_athena_identifier_unique'
                );
            }
        } catch (PDOException $e) {
            echo '  [ATTENTION] users_athena_identifier_unique : ' . $e->getMessage() . "\n";
            $flush();
        }

        if ($columnExists($pdo, 'users', 'athena_identifier')) {
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $max = strlen($alphabet) - 1;
            $sel = $pdo->query("SELECT id FROM users WHERE athena_identifier IS NULL OR TRIM(athena_identifier) = ''");
            $rows = $sel ? ($sel->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            if ($rows !== []) {
                $checkStmt = $pdo->prepare('SELECT 1 FROM users WHERE athena_identifier = ? AND id <> ? LIMIT 1');
                $updStmt = $pdo->prepare('UPDATE users SET athena_identifier = ? WHERE id = ?');
                foreach ($rows as $r) {
                    $uid = (int) ($r['id'] ?? 0);
                    if ($uid < 1) {
                        continue;
                    }
                    $tries = 0;
                    $candidate = '';
                    do {
                        $tries++;
                        $raw = random_bytes(9);
                        $candidate = '';
                        for ($i = 0; $i < 9; $i++) {
                            $candidate .= $alphabet[ord($raw[$i]) % ($max + 1)];
                        }
                        $checkStmt->execute([$candidate, $uid]);
                        $exists = (bool) $checkStmt->fetchColumn();
                    } while ($exists && $tries < 30);
                    if ($candidate !== '') {
                        $updStmt->execute([$candidate, $uid]);
                    }
                }
            }
        }
    }

    // --- app_maintenance (20260404000001) ---
    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_maintenance` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scope` VARCHAR(120) NOT NULL DEFAULT 'global',
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `title` VARCHAR(255) NOT NULL DEFAULT 'Maintenance en cours',
    `message` TEXT NULL,
    `maintenance_code` VARCHAR(80) NULL,
    `starts_at` DATETIME NULL,
    `ends_at` DATETIME NULL,
    `allow_admin_bypass` TINYINT(1) NOT NULL DEFAULT 1,
    `allowed_ips` TEXT NULL,
    `allowed_roles` TEXT NULL,
    `allowed_user_ids` TEXT NULL,
    `message_preset` VARCHAR(80) NULL,
    `ui_variant` VARCHAR(40) NOT NULL DEFAULT 'military',
    `ui_animation` TINYINT(1) NOT NULL DEFAULT 1,
    `notify_members_by_email` TINYINT(1) NOT NULL DEFAULT 0,
    `notify_email_subject` VARCHAR(255) NULL,
    `notify_email_message` TEXT NULL,
    `redirect_url` VARCHAR(255) NULL,
    `http_status` SMALLINT NOT NULL DEFAULT 503,
    `priority` INT NOT NULL DEFAULT 100,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_scope_enabled` (`scope`, `is_enabled`),
    KEY `idx_priority` (`priority`),
    KEY `idx_starts_at` (`starts_at`),
    KEY `idx_ends_at` (`ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'app_maintenance');

    if ($tableExists($pdo, 'app_maintenance')) {
        if (!$columnExists($pdo, 'app_maintenance', 'allowed_user_ids')) {
            $execTry($pdo, 'ALTER TABLE `app_maintenance` ADD COLUMN `allowed_user_ids` TEXT NULL AFTER `allowed_roles`', 'app_maintenance.allowed_user_ids');
        }
        if (!$columnExists($pdo, 'app_maintenance', 'message_preset')) {
            $execTry($pdo, "ALTER TABLE `app_maintenance` ADD COLUMN `message_preset` VARCHAR(80) NULL AFTER `allowed_user_ids`", 'app_maintenance.message_preset');
        }
        if (!$columnExists($pdo, 'app_maintenance', 'ui_variant')) {
            $execTry($pdo, "ALTER TABLE `app_maintenance` ADD COLUMN `ui_variant` VARCHAR(40) NOT NULL DEFAULT 'military' AFTER `message_preset`", 'app_maintenance.ui_variant');
        }
        if (!$columnExists($pdo, 'app_maintenance', 'ui_animation')) {
            $execTry($pdo, 'ALTER TABLE `app_maintenance` ADD COLUMN `ui_animation` TINYINT(1) NOT NULL DEFAULT 1 AFTER `ui_variant`', 'app_maintenance.ui_animation');
        }
        if (!$columnExists($pdo, 'app_maintenance', 'notify_members_by_email')) {
            $execTry($pdo, 'ALTER TABLE `app_maintenance` ADD COLUMN `notify_members_by_email` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ui_animation`', 'app_maintenance.notify_members_by_email');
        }
        if (!$columnExists($pdo, 'app_maintenance', 'notify_email_subject')) {
            $execTry($pdo, 'ALTER TABLE `app_maintenance` ADD COLUMN `notify_email_subject` VARCHAR(255) NULL AFTER `notify_members_by_email`', 'app_maintenance.notify_email_subject');
        }
        if (!$columnExists($pdo, 'app_maintenance', 'notify_email_message')) {
            $execTry($pdo, 'ALTER TABLE `app_maintenance` ADD COLUMN `notify_email_message` TEXT NULL AFTER `notify_email_subject`', 'app_maintenance.notify_email_message');
        }
    }

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `app_maintenance_audit` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `maintenance_id` BIGINT UNSIGNED NOT NULL,
    `action_type` ENUM('create','update','enable','disable','delete') NOT NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `actor_user_id` BIGINT UNSIGNED NULL,
    `actor_ip` VARCHAR(64) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_maintenance_id` (`maintenance_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'app_maintenance_audit');

    // --- Tableau opérationnel : tables de base (20260412000001) ---
    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(180) NOT NULL,
    `description` TEXT NULL,
    `entry_type` ENUM('permanence','info','mission','task','formation') NOT NULL,
    `category_id` BIGINT UNSIGNED NULL,
    `linked_type` ENUM('event','mission','formation','none') NULL,
    `linked_id` BIGINT UNSIGNED NULL,
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `all_day` TINYINT(1) NOT NULL DEFAULT 1,
    `status` ENUM('draft','active','archived','cancelled') NOT NULL DEFAULT 'draft',
    `priority` ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
    `display_order` INT NOT NULL DEFAULT 100,
    `visibility_scope` ENUM('tenant','unit','role','private') NOT NULL DEFAULT 'tenant',
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_planning_entries_tenant_type` (`tenant_id`, `entry_type`),
    KEY `idx_planning_entries_status_dates` (`status`, `start_date`, `end_date`),
    KEY `idx_planning_entries_linked` (`linked_type`, `linked_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entries');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_personnel` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_label` VARCHAR(120) NULL,
    `is_lead` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_planning_personnel` (`planning_entry_id`, `user_id`, `role_label`),
    KEY `idx_planning_personnel_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_personnel');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_assets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `asset_type` VARCHAR(60) NOT NULL,
    `asset_label` VARCHAR(160) NOT NULL,
    `asset_reference` VARCHAR(160) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_planning_assets_entry` (`planning_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_assets');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_notes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `note_type` ENUM('consigne','info','restriction','brief') NOT NULL DEFAULT 'consigne',
    `content` TEXT NOT NULL,
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_planning_notes_entry` (`planning_entry_id`),
    KEY `idx_planning_notes_pinned` (`is_pinned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_notes');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `color` VARCHAR(20) NOT NULL DEFAULT '#334155',
    `icon` VARCHAR(80) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_planning_categories_name` (`tenant_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_categories');

    // --- Enhance operational board (20260412000002) ---
    if ($tableExists($pdo, 'planning_entries')) {
        if (!$columnExists($pdo, 'planning_entries', 'validation_status')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN validation_status ENUM('draft','validated','active','rejected') NOT NULL DEFAULT 'draft' AFTER status
SQL, 'planning_entries.validation_status');
        }
        if (!$columnExists($pdo, 'planning_entries', 'validation_comment')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN validation_comment TEXT NULL AFTER validation_status
SQL, 'planning_entries.validation_comment');
        }
        if (!$columnExists($pdo, 'planning_entries', 'validated_by')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN validated_by BIGINT UNSIGNED NULL AFTER validation_comment
SQL, 'planning_entries.validated_by');
        }
        if (!$columnExists($pdo, 'planning_entries', 'validated_at')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN validated_at DATETIME NULL AFTER validated_by
SQL, 'planning_entries.validated_at');
        }
        if (!$columnExists($pdo, 'planning_entries', 'visibility_unit_id')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN visibility_unit_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER visibility_scope,
    ADD COLUMN visibility_job_role_ids TEXT NULL DEFAULT NULL AFTER visibility_unit_id
SQL, 'planning_entries.visibility_targeting');
        } elseif (!$columnExists($pdo, 'planning_entries', 'visibility_job_role_ids')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN visibility_job_role_ids TEXT NULL DEFAULT NULL AFTER visibility_unit_id
SQL, 'planning_entries.visibility_job_role_ids');
        }
        if (!$columnExists($pdo, 'planning_entries', 'operational_status')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN operational_status ENUM('planned','in_progress','suspended','completed','cancelled') NOT NULL DEFAULT 'planned' AFTER visibility_job_role_ids
SQL, 'planning_entries.operational_status');
        }
        if (!$columnExists($pdo, 'planning_entries', 'security_level')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN security_level ENUM('unit_public','command_restricted','confidential','secret_ops') NOT NULL DEFAULT 'unit_public' AFTER operational_status
SQL, 'planning_entries.security_level');
        }
        if (!$columnExists($pdo, 'planning_entries', 'chief_user_id')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN chief_user_id BIGINT UNSIGNED NULL AFTER created_by
SQL, 'planning_entries.chief_user_id');
        }
        if (!$columnExists($pdo, 'planning_entries', 'deputy_user_id')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN deputy_user_id BIGINT UNSIGNED NULL AFTER chief_user_id
SQL, 'planning_entries.deputy_user_id');
        }
        if (!$columnExists($pdo, 'planning_entries', 'command_chain')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN command_chain VARCHAR(255) NULL AFTER deputy_user_id
SQL, 'planning_entries.command_chain');
        }
        if (!$columnExists($pdo, 'planning_entries', 'accountability_note')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN accountability_note VARCHAR(255) NULL AFTER command_chain
SQL, 'planning_entries.accountability_note');
        }
        if (!$columnExists($pdo, 'planning_entries', 'location_lat')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN location_lat DECIMAL(10,7) NULL AFTER accountability_note
SQL, 'planning_entries.location_lat');
        }
        if (!$columnExists($pdo, 'planning_entries', 'location_lng')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN location_lng DECIMAL(10,7) NULL AFTER location_lat
SQL, 'planning_entries.location_lng');
        }
        if (!$columnExists($pdo, 'planning_entries', 'operation_zone')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN operation_zone VARCHAR(255) NULL AFTER location_lng
SQL, 'planning_entries.operation_zone');
        }
        if (!$columnExists($pdo, 'planning_entries', 'map_link')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN map_link VARCHAR(255) NULL AFTER operation_zone
SQL, 'planning_entries.map_link');
        }
    }

    if ($tableExists($pdo, 'planning_entry_assets')) {
        if (!$columnExists($pdo, 'planning_entry_assets', 'asset_state')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entry_assets
    ADD COLUMN asset_state ENUM('available','engaged','unavailable') NOT NULL DEFAULT 'available' AFTER asset_reference
SQL, 'planning_entry_assets.asset_state');
        }
        if (!$columnExists($pdo, 'planning_entry_assets', 'asset_metadata')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entry_assets
    ADD COLUMN asset_metadata JSON NULL AFTER asset_state
SQL, 'planning_entry_assets.asset_metadata');
        }
    }

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `operational_postures` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `posture_level` ENUM('NORMAL','VIGILANCE','ALERTE','CRISE') NOT NULL DEFAULT 'NORMAL',
    `updated_by` BIGINT UNSIGNED NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_posture_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'operational_postures');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `actor_user_id` BIGINT UNSIGNED NULL,
    `action_type` ENUM('create','update','delete','status_change','assignment','note','validation','template_apply') NOT NULL,
    `summary` VARCHAR(255) NOT NULL,
    `payload_json` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entry_logs_entry` (`planning_entry_id`),
    KEY `idx_entry_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_logs');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_templates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(160) NOT NULL,
    `template_type` ENUM('permanence_opj','mission_judiciaire','instruction','dispositif_securite','exercice','custom') NOT NULL DEFAULT 'custom',
    `payload_json` JSON NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_templates_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_templates');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_dependencies` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entry_id` BIGINT UNSIGNED NOT NULL,
    `depends_on_entry_id` BIGINT UNSIGNED NOT NULL,
    `dependency_type` ENUM('blocked_by','requires_training','prerequisite') NOT NULL DEFAULT 'blocked_by',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entry_dependency` (`entry_id`, `depends_on_entry_id`, `dependency_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_dependencies');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_comments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `author_user_id` BIGINT UNSIGNED NULL,
    `content` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entry_comments_entry` (`planning_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_comments');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_attachments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `uploaded_by` BIGINT UNSIGNED NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(120) NULL,
    `file_size` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entry_attachments_entry` (`planning_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_attachments');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `target_scope` ENUM('role','unit','mission') NOT NULL,
    `target_value` VARCHAR(120) NOT NULL,
    `channel` ENUM('dashboard','email','push','atak') NOT NULL DEFAULT 'dashboard',
    `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entry_notifications_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_notifications');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_shares` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `source_tenant_id` BIGINT UNSIGNED NOT NULL,
    `target_tenant_id` BIGINT UNSIGNED NOT NULL,
    `share_mode` ENUM('read','contribute','command') NOT NULL DEFAULT 'read',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entry_share` (`planning_entry_id`, `target_tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_shares');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_training_requirements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `training_slug` VARCHAR(160) NOT NULL,
    `is_mandatory` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entry_training_req` (`planning_entry_id`, `training_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_training_requirements');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_integrations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `source_system` ENUM('comspec','arma','atak') NOT NULL,
    `external_ref` VARCHAR(190) NOT NULL,
    `last_payload` JSON NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entry_integration` (`source_system`, `external_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_integrations');

    // --- Advanced workflows (20260412000003) ---
    if ($tableExists($pdo, 'planning_entries')) {
        if (!$columnExists($pdo, 'planning_entries', 'frago_parent_entry_id')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN frago_parent_entry_id BIGINT UNSIGNED NULL AFTER linked_id
SQL, 'planning_entries.frago_parent_entry_id');
        }
        if (!$columnExists($pdo, 'planning_entries', 'frago_version')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN frago_version INT NOT NULL DEFAULT 1 AFTER frago_parent_entry_id
SQL, 'planning_entries.frago_version');
        }
        if (!$columnExists($pdo, 'planning_entries', 'replacement_user_id')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN replacement_user_id BIGINT UNSIGNED NULL AFTER deputy_user_id
SQL, 'planning_entries.replacement_user_id');
        }
        if (!$columnExists($pdo, 'planning_entries', 'replacement_auto_activate')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN replacement_auto_activate TINYINT(1) NOT NULL DEFAULT 0 AFTER replacement_user_id
SQL, 'planning_entries.replacement_auto_activate');
        }
        if (!$columnExists($pdo, 'planning_entries', 'phase_current')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN phase_current ENUM('phase_1','phase_2','phase_3') NOT NULL DEFAULT 'phase_1' AFTER operational_status
SQL, 'planning_entries.phase_current');
        }
        if (!$columnExists($pdo, 'planning_entries', 'phase_rules_json')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN phase_rules_json JSON NULL AFTER phase_current
SQL, 'planning_entries.phase_rules_json');
        }
        if (!$columnExists($pdo, 'planning_entries', 'dossier_ref')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN dossier_ref VARCHAR(120) NULL AFTER map_link
SQL, 'planning_entries.dossier_ref');
        }
        if (!$columnExists($pdo, 'planning_entries', 'legal_constraints')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN legal_constraints TEXT NULL AFTER dossier_ref
SQL, 'planning_entries.legal_constraints');
        }
        if (!$columnExists($pdo, 'planning_entries', 'fire_window_start')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN fire_window_start DATETIME NULL AFTER legal_constraints
SQL, 'planning_entries.fire_window_start');
        }
        if (!$columnExists($pdo, 'planning_entries', 'fire_window_end')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN fire_window_end DATETIME NULL AFTER fire_window_start
SQL, 'planning_entries.fire_window_end');
        }
        if (!$columnExists($pdo, 'planning_entries', 'realtime_external_ref')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN realtime_external_ref VARCHAR(190) NULL AFTER fire_window_end
SQL, 'planning_entries.realtime_external_ref');
        }
    }

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_versions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `version_number` INT NOT NULL,
    `payload_json` JSON NOT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entry_version` (`planning_entry_id`, `version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_versions');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_checklists` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `is_required` TINYINT(1) NOT NULL DEFAULT 1,
    `is_done` TINYINT(1) NOT NULL DEFAULT 0,
    `done_by` BIGINT UNSIGNED NULL,
    `done_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_entry_checklist_entry` (`planning_entry_id`),
    KEY `idx_entry_checklist_done` (`is_done`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_checklists');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_skills` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `skill_code` VARCHAR(80) NOT NULL,
    `is_mandatory` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entry_skill` (`planning_entry_id`, `skill_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_skills');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `personnel_skill_validity` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `skill_code` VARCHAR(80) NOT NULL,
    `valid_until` DATE NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_skill_validity_lookup` (`tenant_id`, `user_id`, `skill_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'personnel_skill_validity');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_tags` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `tag` VARCHAR(80) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entry_tag` (`planning_entry_id`, `tag`),
    KEY `idx_entry_tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_tags');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_risks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `risk_level` ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
    `risk_label` VARCHAR(180) NOT NULL,
    `mitigation` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entry_risk_level` (`risk_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_entry_risks');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_realtime_stream` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `entry_id` BIGINT UNSIGNED NULL,
    `event_type` VARCHAR(60) NOT NULL,
    `payload_json` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_realtime_stream_tenant` (`tenant_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'planning_realtime_stream');

    // --- units.orbat_mask_mode (20260412000004) ---
    if ($tableExists($pdo, 'units') && !$columnExists($pdo, 'units', 'orbat_mask_mode')) {
        if ($columnExists($pdo, 'units', 'show_on_public_page')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE units
    ADD COLUMN orbat_mask_mode VARCHAR(32) NOT NULL DEFAULT 'none' AFTER show_on_public_page
SQL, 'units.orbat_mask_mode');
        } else {
            $execTry($pdo, <<<'SQL'
ALTER TABLE units
    ADD COLUMN orbat_mask_mode VARCHAR(32) NOT NULL DEFAULT 'none'
SQL, 'units.orbat_mask_mode');
        }
    }

    // --- ORBAT affichage (20260412100001) ---
    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `tenant_orbat_chart_types` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `slug` VARCHAR(64) NOT NULL,
    `label` VARCHAR(120) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_tenant_orbat_chart_slug` (`tenant_id`, `slug`),
    CONSTRAINT `tenant_orbat_chart_types_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'tenant_orbat_chart_types');

    if ($tableExists($pdo, 'units')) {
        if (!$columnExists($pdo, 'units', 'orbat_display_type')) {
            $execTry($pdo, <<<'SQL'
ALTER TABLE units ADD COLUMN orbat_display_type VARCHAR(64) NOT NULL DEFAULT 'command' AFTER type
SQL, 'units.orbat_display_type');
        }
        $afterIcon = 'orbat_display_type';
        if ($columnExists($pdo, 'units', 'orbat_mask_mode')) {
            $afterIcon = 'orbat_mask_mode';
        }
        if (!$columnExists($pdo, 'units', 'orbat_icon_path')) {
            $execTry($pdo, 'ALTER TABLE units ADD COLUMN orbat_icon_path VARCHAR(512) NULL AFTER ' . $afterIcon, 'units.orbat_icon_path');
        }
        if (!$columnExists($pdo, 'units', 'orbat_image_path')) {
            $execTry($pdo, 'ALTER TABLE units ADD COLUMN orbat_image_path VARCHAR(512) NULL AFTER orbat_icon_path', 'units.orbat_image_path');
        }
        if (!$columnExists($pdo, 'units', 'orbat_details')) {
            $execTry($pdo, 'ALTER TABLE units ADD COLUMN orbat_details TEXT NULL AFTER orbat_image_path', 'units.orbat_details');
        }

        $st = $pdo->query("SELECT 1 FROM units WHERE type IN ('command','alpha','bravo','support','special') LIMIT 1");
        if ($st && $st->fetch()) {
            $st->closeCursor();
            $execTry($pdo, <<<'SQL'
UPDATE units
SET orbat_display_type = IF(
    type IN ('command','alpha','bravo','support','special'),
    type,
    'command'
)
SQL, 'units backfill orbat_display_type');
            $execTry($pdo, <<<'SQL'
UPDATE units
SET type = IF(
    type IN ('command','alpha','bravo','support','special'),
    'unit',
    type
)
SQL, 'units normalize type');
        } elseif ($st) {
            $st->closeCursor();
        }
    }

    // --- entry_type : manifestation / flash (20260412120000) ---
    if ($tableExists($pdo, 'planning_entries')) {
        $execTry($pdo, <<<'SQL'
ALTER TABLE planning_entries
    MODIFY COLUMN entry_type ENUM(
        'permanence','info','mission','task','formation',
        'manifestation','flash_info'
    ) NOT NULL
SQL, 'planning_entries.entry_type enum');
    }

    // --- Permission operational.board.view pour member (20260412120100) ---
    if ($tableExists($pdo, 'permissions') && $tableExists($pdo, 'roles') && $tableExists($pdo, 'role_permissions')) {
        $execTry($pdo, <<<'SQL'
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.tenant_id = r.tenant_id
WHERE r.slug = 'member' AND p.slug = 'operational.board.view'
SQL, 'operational.board.view pour member');
    }

    // --- E-mail tenant (20260412000005) ---
    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `tenant_email_templates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `kind` ENUM('orbat','mission','activity','custom') NOT NULL,
    `name` VARCHAR(160) NOT NULL,
    `subject` VARCHAR(500) NOT NULL,
    `body_html` MEDIUMTEXT NOT NULL,
    `body_text` TEXT NULL,
    `is_prefab` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tet_tenant_kind` (`tenant_id`, `kind`),
    CONSTRAINT `tet_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'tenant_email_templates');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `tenant_email_recipient_groups` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(160) NOT NULL,
    `description` VARCHAR(500) NULL,
    `definition_json` JSON NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_terg_tenant` (`tenant_id`),
    CONSTRAINT `terg_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'tenant_email_recipient_groups');

    $execTry($pdo, <<<'SQL'
CREATE TABLE IF NOT EXISTS `tenant_email_campaigns` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `kind` ENUM('orbat','mission','activity','custom') NOT NULL,
    `template_id` INT UNSIGNED NULL,
    `subject_snapshot` VARCHAR(500) NOT NULL,
    `sender_user_id` INT UNSIGNED NOT NULL,
    `recipient_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('queued','completed','failed_partial') NOT NULL DEFAULT 'queued',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tec_tenant_created` (`tenant_id`, `created_at`),
    KEY `idx_tec_template` (`template_id`),
    CONSTRAINT `tec_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `tec_template_fk` FOREIGN KEY (`template_id`) REFERENCES `tenant_email_templates` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL, 'tenant_email_campaigns');

    if ($tableExists($pdo, 'email_deliveries') && !$columnExists($pdo, 'email_deliveries', 'campaign_id')) {
        $execTry($pdo, <<<'SQL'
ALTER TABLE `email_deliveries`
    ADD COLUMN `campaign_id` INT UNSIGNED NULL AFTER `tenant_id`,
    ADD KEY `idx_email_deliveries_campaign` (`campaign_id`),
    ADD CONSTRAINT `email_deliveries_campaign_fk` FOREIGN KEY (`campaign_id`) REFERENCES `tenant_email_campaigns` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
SQL, 'email_deliveries.campaign_id');
    }

    try {
        $autoBootstrap = $root . '/bootstrap/autoload.php';
        if (is_file($autoBootstrap) && !class_exists(\App\Services\Community\TenantDefaultRoleDefinitions::class, false)) {
            require_once $autoBootstrap;
        }
        if (class_exists(\App\Services\Community\TenantDefaultRoleDefinitions::class)) {
            \App\Services\Community\TenantDefaultRoleDefinitions::applyCanonicalEnglishLabels($pdo, null);
            echo "[OK] Libellés anglais (label_en) des rôles synchronisés.\n";
            $flush();
        }
    } catch (Throwable $e) {
        echo '[ATTENTION] Libellés anglais rôles : ' . $e->getMessage() . "\n";
        $flush();
    }

    echo "[OK] Extensions schéma (hors schema.sql) : terminé.\n";
    $flush();
}
