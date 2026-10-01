<?php

declare(strict_types=1);

/**
 * Fondation org duty / workbench : tâches universelles, duty mission, roster,
 * template de capacité sur les billets ORBAT.
 */
function run_org_duty_workbench_migration(PDO $pdo): void
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
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };
    $execTry = static function (string $sql, string $label) use ($pdo): void {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            echo '  [ATTENTION] ' . $label . ' : ' . $e->getMessage() . "\n";
        }
    };

    if ($hasTable('orbat_billets') && !$hasColumn('orbat_billets', 'capability_template')) {
        $execTry(
            "ALTER TABLE orbat_billets ADD COLUMN capability_template VARCHAR(64) NULL AFTER function_label",
            'orbat_billets.capability_template'
        );
    }

    if (!$hasTable('work_tasks')) {
        $execTry(
            "CREATE TABLE work_tasks (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                type VARCHAR(64) NOT NULL DEFAULT 'generic',
                title VARCHAR(191) NOT NULL,
                description TEXT NULL,
                created_by INT UNSIGNED NULL,
                assigned_user_id INT UNSIGNED NULL,
                assigned_billet_id INT UNSIGNED NULL,
                assigned_unit_id INT UNSIGNED NULL,
                assigned_position_slug VARCHAR(80) NULL,
                priority VARCHAR(16) NOT NULL DEFAULT 'normal',
                status VARCHAR(32) NOT NULL DEFAULT 'open',
                due_at DATETIME NULL,
                acknowledged_at DATETIME NULL,
                started_at DATETIME NULL,
                completed_at DATETIME NULL,
                linked_entity_type VARCHAR(64) NULL,
                linked_entity_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_wt_tenant_status (tenant_id, status),
                KEY idx_wt_assigned_user (tenant_id, assigned_user_id, status),
                KEY idx_wt_assigned_billet (tenant_id, assigned_billet_id, status),
                KEY idx_wt_position_slug (tenant_id, assigned_position_slug, status),
                KEY idx_wt_due (tenant_id, due_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'work_tasks'
        );
    }

    if (!$hasTable('mission_duty_assignments')) {
        $execTry(
            "CREATE TABLE mission_duty_assignments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                operation_id BIGINT UNSIGNED NULL,
                mission_label VARCHAR(191) NOT NULL DEFAULT '',
                user_id INT UNSIGNED NOT NULL,
                organic_billet_id INT UNSIGNED NULL,
                duty_billet_id INT UNSIGNED NULL,
                duty_title VARCHAR(150) NOT NULL,
                duty_callsign VARCHAR(80) NULL,
                capability_template VARCHAR(64) NULL,
                starts_at DATETIME NOT NULL,
                ends_at DATETIME NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_mda_tenant_user (tenant_id, user_id, status),
                KEY idx_mda_operation (tenant_id, operation_id, status),
                KEY idx_mda_active_window (tenant_id, status, starts_at, ends_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'mission_duty_assignments'
        );
    }

    if (!$hasTable('duty_roster_slots')) {
        $execTry(
            "CREATE TABLE duty_roster_slots (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                position_slug VARCHAR(80) NOT NULL,
                capability_template VARCHAR(64) NULL,
                label VARCHAR(120) NOT NULL DEFAULT '',
                user_id INT UNSIGNED NOT NULL,
                starts_at DATETIME NOT NULL,
                ends_at DATETIME NOT NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_drs_window (tenant_id, position_slug, starts_at, ends_at),
                KEY idx_drs_user (tenant_id, user_id, starts_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'duty_roster_slots'
        );
    }

    echo "  [OK] org_duty_workbench_migration\n";
}
