<?php

declare(strict_types=1);

/**
 * Parcours RH (phases, jeux de règles, conditions, journal). Aucune règle créée.
 */
function run_personnel_phase_rules_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('personnel_phase_definitions')) {
        $pdo->exec(
            "CREATE TABLE personnel_phase_definitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                label VARCHAR(120) NOT NULL,
                position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                target_member_status VARCHAR(80) NOT NULL DEFAULT 'En formation',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_ppd_tenant_pos (tenant_id, position, is_active),
                CONSTRAINT fk_ppd_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_phase_rule_sets')) {
        $pdo->exec(
            "CREATE TABLE personnel_phase_rule_sets (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                phase_id INT UNSIGNED NOT NULL,
                logic ENUM('all','any') NOT NULL DEFAULT 'all',
                effect ENUM('manual_gate','automatic') NOT NULL DEFAULT 'manual_gate',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_pprs_phase (phase_id),
                KEY idx_pprs_tenant (tenant_id, is_active),
                CONSTRAINT fk_pprs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_pprs_phase FOREIGN KEY (phase_id) REFERENCES personnel_phase_definitions (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_phase_rule_conditions')) {
        $pdo->exec(
            "CREATE TABLE personnel_phase_rule_conditions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                rule_set_id INT UNSIGNED NOT NULL,
                condition_type VARCHAR(64) NOT NULL,
                qualification_id INT UNSIGNED NULL,
                training_module_id INT UNSIGNED NULL,
                threshold_value DECIMAL(10,2) NOT NULL DEFAULT 0,
                window_days SMALLINT UNSIGNED NULL,
                require_validity TINYINT(1) NOT NULL DEFAULT 0,
                hour_category VARCHAR(80) NULL,
                session_kind VARCHAR(40) NULL,
                position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_pprc_set (rule_set_id, position),
                KEY idx_pprc_tenant (tenant_id),
                CONSTRAINT fk_pprc_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_pprc_set FOREIGN KEY (rule_set_id) REFERENCES personnel_phase_rule_sets (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_phase_transitions')) {
        $pdo->exec(
            "CREATE TABLE personnel_phase_transitions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                from_phase_id INT UNSIGNED NULL,
                to_phase_id INT UNSIGNED NOT NULL,
                rule_set_id INT UNSIGNED NULL,
                trigger_kind ENUM('manual','automatic','bootstrap','admin_override') NOT NULL DEFAULT 'manual',
                actor_user_id INT UNSIGNED NULL,
                override_reason VARCHAR(500) NULL,
                evaluation_snapshot_json JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_ppt_tenant_user (tenant_id, user_id, created_at),
                KEY idx_ppt_tenant_to (tenant_id, to_phase_id),
                CONSTRAINT fk_ppt_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_ppt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_phase_auto_errors')) {
        $pdo->exec(
            "CREATE TABLE personnel_phase_auto_errors (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                phase_id INT UNSIGNED NULL,
                error_code VARCHAR(64) NOT NULL,
                error_label VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_ppae_tenant_open (tenant_id, resolved_at, created_at),
                KEY idx_ppae_user (tenant_id, user_id),
                CONSTRAINT fk_ppae_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if ($hasTable('personnel_profiles') && !$hasColumn('personnel_profiles', 'current_phase_id')) {
        $pdo->exec(
            'ALTER TABLE personnel_profiles
             ADD COLUMN current_phase_id INT UNSIGNED NULL DEFAULT NULL AFTER rp_followup_stage,
             ADD KEY idx_pp_current_phase (current_phase_id)'
        );
    }
}
