<?php

declare(strict_types=1);

/**
 * PASS RH : packs de conditions réutilisables (poste, avancement, notation).
 */
function run_personnel_pass_migration(PDO $pdo): void
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
    $execTry = static function (string $sql, string $label) use ($pdo): void {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            echo '  [ATTENTION] ' . $label . ' : ' . $e->getMessage() . "\n";
        }
    };

    if (!$hasTable('personnel_passes')) {
        $pdo->exec(
            "CREATE TABLE personnel_passes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(64) NOT NULL,
                label VARCHAR(160) NOT NULL,
                description TEXT NULL,
                logic ENUM('all','any') NOT NULL DEFAULT 'all',
                use_for_post TINYINT(1) NOT NULL DEFAULT 1,
                use_for_advancement TINYINT(1) NOT NULL DEFAULT 1,
                use_for_notation TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_pass_tenant_code (tenant_id, code),
                KEY idx_pass_tenant_active (tenant_id, is_active),
                CONSTRAINT fk_pass_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_pass_conditions')) {
        $pdo->exec(
            "CREATE TABLE personnel_pass_conditions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                pass_id INT UNSIGNED NOT NULL,
                condition_type VARCHAR(64) NOT NULL,
                qualification_id INT UNSIGNED NULL,
                training_module_id INT UNSIGNED NULL,
                grade_id INT UNSIGNED NULL,
                threshold_value DECIMAL(10,2) NOT NULL DEFAULT 0,
                window_days SMALLINT UNSIGNED NULL,
                require_validity TINYINT(1) NOT NULL DEFAULT 0,
                hour_category VARCHAR(80) NULL,
                session_kind VARCHAR(40) NULL,
                avis_kind VARCHAR(40) NULL,
                bilan_kind VARCHAR(40) NULL,
                position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_ppc_pass (pass_id, position),
                KEY idx_ppc_tenant (tenant_id),
                CONSTRAINT fk_ppc_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_ppc_pass FOREIGN KEY (pass_id) REFERENCES personnel_passes (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_hierarchical_opinions')) {
        $pdo->exec(
            "CREATE TABLE personnel_hierarchical_opinions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                subject_user_id INT UNSIGNED NOT NULL,
                pass_id INT UNSIGNED NULL,
                avis_kind VARCHAR(40) NOT NULL,
                opinion ENUM('favorable','defavorable','reserve') NOT NULL DEFAULT 'favorable',
                author_user_id INT UNSIGNED NOT NULL,
                note VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_pho_subject (tenant_id, subject_user_id, avis_kind, created_at),
                KEY idx_pho_pass (tenant_id, pass_id),
                CONSTRAINT fk_pho_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_pho_subject FOREIGN KEY (subject_user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if ($hasTable('recruitment_openings') && !$hasColumn('recruitment_openings', 'required_pass_id')) {
        $execTry(
            'ALTER TABLE recruitment_openings
             ADD COLUMN required_pass_id INT UNSIGNED NULL DEFAULT NULL AFTER personnel_job_role_id,
             ADD KEY idx_ro_required_pass (required_pass_id)',
            'recruitment_openings.required_pass_id'
        );
    }

    if ($hasTable('orbat_billets') && !$hasColumn('orbat_billets', 'required_pass_id')) {
        $execTry(
            'ALTER TABLE orbat_billets
             ADD COLUMN required_pass_id INT UNSIGNED NULL DEFAULT NULL AFTER required_pack_id,
             ADD KEY idx_ob_required_pass (required_pass_id)',
            'orbat_billets.required_pass_id'
        );
    }

    if ($hasTable('grade_definitions') && !$hasColumn('grade_definitions', 'required_pass_id')) {
        $execTry(
            'ALTER TABLE grade_definitions
             ADD COLUMN required_pass_id INT UNSIGNED NULL DEFAULT NULL AFTER required_qualification_level_id,
             ADD KEY idx_gd_required_pass (required_pass_id)',
            'grade_definitions.required_pass_id'
        );
    }

    echo "  [OK] personnel_pass_migration\n";
}
