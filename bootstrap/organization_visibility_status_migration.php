<?php

declare(strict_types=1);

/**
 * Confidentialité (personnels / unités / affectations / qualifications)
 * et statuts administratifs des structures ORBAT.
 * Idempotent.
 */
function run_organization_visibility_status_migration(PDO $pdo): void
{
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $columnType = static function (string $table, string $column) use ($pdo): ?string {
        $st = $pdo->prepare(
            'SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ? strtolower((string) ($row['DATA_TYPE'] ?? '')) : null;
    };

    $columnLength = static function (string $table, string $column) use ($pdo): ?int {
        $st = $pdo->prepare(
            'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);
        $v = $st->fetchColumn();

        return $v === false || $v === null ? null : (int) $v;
    };

    $execTry = static function (string $sql, string $label) use ($pdo): void {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            echo '  [ATTENTION] ' . $label . ' : ' . $e->getMessage() . "\n";
        }
    };

    // --- units : statut administratif + note + visibilité métier ---
    if ($tableExists('units')) {
        if (!$columnExists('units', 'admin_status')) {
            $after = $columnExists('units', 'orbat_mask_mode') ? 'orbat_mask_mode' : 'show_on_public_page';
            if ($columnExists('units', $after)) {
                $execTry(
                    "ALTER TABLE units ADD COLUMN admin_status VARCHAR(32) NOT NULL DEFAULT 'active' AFTER {$after}",
                    'units.admin_status'
                );
            } else {
                $execTry(
                    "ALTER TABLE units ADD COLUMN admin_status VARCHAR(32) NOT NULL DEFAULT 'active'",
                    'units.admin_status'
                );
            }
        }
        if (!$columnExists('units', 'admin_status_note')) {
            $after = $columnExists('units', 'admin_status') ? 'admin_status' : null;
            $sql = $after
                ? "ALTER TABLE units ADD COLUMN admin_status_note VARCHAR(500) NULL AFTER {$after}"
                : 'ALTER TABLE units ADD COLUMN admin_status_note VARCHAR(500) NULL';
            $execTry($sql, 'units.admin_status_note');
        }
        if (!$columnExists('units', 'visibility_level')) {
            $after = $columnExists('units', 'admin_status_note')
                ? 'admin_status_note'
                : ($columnExists('units', 'admin_status') ? 'admin_status' : null);
            $sql = $after
                ? "ALTER TABLE units ADD COLUMN visibility_level VARCHAR(32) NOT NULL DEFAULT 'normal' AFTER {$after}"
                : "ALTER TABLE units ADD COLUMN visibility_level VARCHAR(32) NOT NULL DEFAULT 'normal'";
            $execTry($sql, 'units.visibility_level');
        }
        if (!$columnExists('units', 'visibility_propagate')) {
            $after = $columnExists('units', 'visibility_level') ? 'visibility_level' : null;
            $sql = $after
                ? "ALTER TABLE units ADD COLUMN visibility_propagate TINYINT(1) NOT NULL DEFAULT 1 AFTER {$after}"
                : 'ALTER TABLE units ADD COLUMN visibility_propagate TINYINT(1) NOT NULL DEFAULT 1';
            $execTry($sql, 'units.visibility_propagate');
        }
        if (!$columnExists('units', 'strength_display_mode')) {
            $after = $columnExists('units', 'visibility_propagate') ? 'visibility_propagate' : null;
            $sql = $after
                ? "ALTER TABLE units ADD COLUMN strength_display_mode VARCHAR(32) NOT NULL DEFAULT 'visible_only' AFTER {$after}"
                : "ALTER TABLE units ADD COLUMN strength_display_mode VARCHAR(32) NOT NULL DEFAULT 'visible_only'";
            $execTry($sql, 'units.strength_display_mode');
        }

        // Synchroniser visibility_level depuis orbat_mask_mode existant
        if ($columnExists('units', 'visibility_level') && $columnExists('units', 'orbat_mask_mode')) {
            $execTry(
                "UPDATE units SET visibility_level = CASE
                    WHEN orbat_mask_mode = 'hidden_all' THEN 'hidden'
                    WHEN orbat_mask_mode = 'anonymize' THEN 'anonymized'
                    WHEN orbat_mask_mode IN ('scope_section','scope_team','scope_role') THEN 'restricted'
                    ELSE 'normal'
                END
                WHERE visibility_level = 'normal' AND orbat_mask_mode <> 'none'",
                'units.visibility_level sync from mask'
            );
        }
    }

    // --- personnel_profiles.visibility_level ---
    if ($tableExists('personnel_profiles') && !$columnExists('personnel_profiles', 'visibility_level')) {
        $execTry(
            "ALTER TABLE personnel_profiles ADD COLUMN visibility_level VARCHAR(32) NOT NULL DEFAULT 'normal' AFTER primary_unit_id",
            'personnel_profiles.visibility_level'
        );
    }
    if ($tableExists('personnel_profiles') && !$columnExists('personnel_profiles', 'assignment_visibility')) {
        $after = $columnExists('personnel_profiles', 'visibility_level') ? 'visibility_level' : 'primary_unit_id';
        $execTry(
            "ALTER TABLE personnel_profiles ADD COLUMN assignment_visibility VARCHAR(32) NOT NULL DEFAULT 'normal' AFTER {$after}",
            'personnel_profiles.assignment_visibility'
        );
    }
    if ($tableExists('personnel_profiles') && !$columnExists('personnel_profiles', 'anonymized_label')) {
        $after = $columnExists('personnel_profiles', 'assignment_visibility')
            ? 'assignment_visibility'
            : ($columnExists('personnel_profiles', 'visibility_level') ? 'visibility_level' : 'primary_unit_id');
        $execTry(
            "ALTER TABLE personnel_profiles ADD COLUMN anonymized_label VARCHAR(120) NULL AFTER {$after}",
            'personnel_profiles.anonymized_label'
        );
    }

    // --- personnel_assignments.visibility_level ---
    if ($tableExists('personnel_assignments') && !$columnExists('personnel_assignments', 'visibility_level')) {
        $execTry(
            "ALTER TABLE personnel_assignments ADD COLUMN visibility_level VARCHAR(32) NOT NULL DEFAULT 'normal' AFTER status",
            'personnel_assignments.visibility_level'
        );
    }

    // --- personnel_qualifications.visibility_level ---
    if ($tableExists('personnel_qualifications') && !$columnExists('personnel_qualifications', 'visibility_level')) {
        $execTry(
            "ALTER TABLE personnel_qualifications ADD COLUMN visibility_level VARCHAR(32) NOT NULL DEFAULT 'normal' AFTER status",
            'personnel_qualifications.visibility_level'
        );
    }

    // --- Historique des changements de visibilité / statut ---
    if (!$tableExists('organization_visibility_history')) {
        $execTry(
            "CREATE TABLE organization_visibility_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                subject_type VARCHAR(32) NOT NULL,
                subject_id INT UNSIGNED NOT NULL,
                field_name VARCHAR(64) NOT NULL,
                old_value VARCHAR(64) NULL,
                new_value VARCHAR(64) NULL,
                reason VARCHAR(500) NULL,
                actor_user_id INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_ovh_tenant_subject (tenant_id, subject_type, subject_id, created_at),
                KEY idx_ovh_actor (actor_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'organization_visibility_history'
        );
    }

    if (!$tableExists('unit_admin_status_history')) {
        $execTry(
            "CREATE TABLE unit_admin_status_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                unit_id INT UNSIGNED NOT NULL,
                old_status VARCHAR(32) NULL,
                new_status VARCHAR(32) NOT NULL,
                reason VARCHAR(500) NULL,
                comment TEXT NULL,
                effective_at DATETIME NULL,
                actor_user_id INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_uash_tenant_unit (tenant_id, unit_id, created_at),
                KEY idx_uash_actor (actor_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'unit_admin_status_history'
        );
    }

    // --- Descriptions trop courtes (VARCHAR 500) → TEXT ---
    foreach ([
        ['personnel_job_roles', 'description'],
        ['positions', 'description'],
        ['roles', 'description'],
    ] as [$table, $col]) {
        if (!$tableExists($table) || !$columnExists($table, $col)) {
            continue;
        }
        $type = $columnType($table, $col);
        $len = $columnLength($table, $col);
        if ($type === 'varchar' && $len !== null && $len <= 500) {
            $execTry(
                "ALTER TABLE `{$table}` MODIFY COLUMN `{$col}` TEXT NULL",
                "{$table}.{$col} TEXT"
            );
        }
    }

    echo "  [OK] organization_visibility_status_migration\n";
}
