<?php

declare(strict_types=1);

/**
 * Catalogue de grades canonique (multi-pays / multi-branches).
 * nato_code est une donnée de référence — jamais dérivé de hierarchy_order.
 */
function run_rank_catalog_migration(PDO $pdo): void
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

    if (!$hasTable('rank_catalog')) {
        $pdo->exec(
            "CREATE TABLE rank_catalog (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                country_code CHAR(2) NOT NULL,
                branch VARCHAR(32) NOT NULL DEFAULT 'ARMY',
                canonical_name VARCHAR(120) NOT NULL,
                short_name VARCHAR(40) NOT NULL,
                category VARCHAR(32) NOT NULL,
                nato_code VARCHAR(16) DEFAULT NULL,
                us_equivalent VARCHAR(16) DEFAULT NULL,
                hierarchy_order INT NOT NULL DEFAULT 0,
                is_officer TINYINT(1) NOT NULL DEFAULT 0,
                is_nco TINYINT(1) NOT NULL DEFAULT 0,
                is_enlisted TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                verification_status ENUM('VERIFIED','UNVERIFIED','INVALID','CUSTOM') NOT NULL DEFAULT 'UNVERIFIED',
                reference_source VARCHAR(255) DEFAULT NULL,
                reference_version VARCHAR(64) DEFAULT NULL,
                verified_at DATETIME DEFAULT NULL,
                verified_by VARCHAR(120) DEFAULT NULL,
                legacy_grade_code VARCHAR(32) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_rank_catalog_identity (country_code, branch, short_name, canonical_name),
                KEY idx_rank_catalog_nato (nato_code),
                KEY idx_rank_catalog_order (country_code, branch, hierarchy_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('tenant_ranks')) {
        $pdo->exec(
            "CREATE TABLE tenant_ranks (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                rank_catalog_id INT UNSIGNED DEFAULT NULL,
                custom_rank TINYINT(1) NOT NULL DEFAULT 0,
                custom_name VARCHAR(120) DEFAULT NULL,
                custom_short_name VARCHAR(40) DEFAULT NULL,
                custom_order INT DEFAULT NULL,
                custom_nato_code VARCHAR(16) DEFAULT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_tenant_rank_catalog (tenant_id, rank_catalog_id),
                KEY idx_tenant_ranks_tenant (tenant_id, enabled),
                CONSTRAINT tenant_ranks_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT tenant_ranks_catalog_fk FOREIGN KEY (rank_catalog_id) REFERENCES rank_catalog (id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_rank_history')) {
        $pdo->exec(
            "CREATE TABLE personnel_rank_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                personnel_id INT UNSIGNED NOT NULL,
                tenant_rank_id INT UNSIGNED DEFAULT NULL,
                legacy_grade_id BIGINT UNSIGNED DEFAULT NULL,
                effective_from DATE NOT NULL,
                effective_to DATE DEFAULT NULL,
                granted_by INT UNSIGNED DEFAULT NULL,
                reason VARCHAR(255) DEFAULT NULL,
                promotion_request_id INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_rank_hist_user (tenant_id, personnel_id, effective_to),
                CONSTRAINT rank_hist_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT rank_hist_user_fk FOREIGN KEY (personnel_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('rank_equivalencies')) {
        $pdo->exec(
            "CREATE TABLE rank_equivalencies (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                source_rank_id INT UNSIGNED NOT NULL,
                target_country_code CHAR(2) NOT NULL,
                target_branch VARCHAR(32) DEFAULT NULL,
                target_rank_id INT UNSIGNED DEFAULT NULL,
                equivalence_type VARCHAR(32) NOT NULL DEFAULT 'NATO_PEER',
                confidence ENUM('HIGH','MEDIUM','LOW','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
                source VARCHAR(255) DEFAULT NULL,
                validated_by VARCHAR(120) DEFAULT NULL,
                validated_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_rank_equiv_source (source_rank_id),
                CONSTRAINT rank_equiv_source_fk FOREIGN KEY (source_rank_id) REFERENCES rank_catalog (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('rank_migration_audit')) {
        $pdo->exec(
            "CREATE TABLE rank_migration_audit (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                old_rank_id BIGINT UNSIGNED DEFAULT NULL,
                old_name VARCHAR(150) DEFAULT NULL,
                old_nato_code VARCHAR(16) DEFAULT NULL,
                new_rank_id INT UNSIGNED DEFAULT NULL,
                new_nato_code VARCHAR(16) DEFAULT NULL,
                migration_status ENUM('MAPPED','REPAIRED','AMBIGUOUS','UNVERIFIED','SKIPPED','INVALID') NOT NULL,
                reason VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_rank_mig_status (migration_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /* Pont vers le référentiel grades existant. */
    $gradeTable = null;
    foreach (['grades', 'grades_referentiel'] as $t) {
        if ($hasTable($t) && $hasColumn($t, 'grade_system_id')) {
            $gradeTable = $t;
            break;
        }
    }
    if ($gradeTable !== null && !$hasColumn($gradeTable, 'rank_catalog_id')) {
        try {
            $pdo->exec("ALTER TABLE `{$gradeTable}` ADD COLUMN rank_catalog_id INT UNSIGNED NULL AFTER label_otan");
        } catch (\Throwable) {
        }
    }
    if ($gradeTable !== null && !$hasColumn($gradeTable, 'otan_verification_status')) {
        try {
            $pdo->exec(
                "ALTER TABLE `{$gradeTable}` ADD COLUMN otan_verification_status
                 ENUM('VERIFIED','UNVERIFIED','INVALID','CUSTOM') NOT NULL DEFAULT 'UNVERIFIED' AFTER rank_catalog_id"
            );
        } catch (\Throwable) {
        }
    }

    echo "  [OK] rank_catalog (catalogue, tenant_ranks, history, equivalencies, migration_audit)\n";

    /* Seed FR ARMY (OTAN explicites) + Gendarmerie (OTAN null tant que non validé). */
    seed_rank_catalog_defaults($pdo);
}

/**
 * Seeds idempotents — nato_code jamais dérivé de hierarchy_order.
 */
function seed_rank_catalog_defaults(PDO $pdo): void
{
    $exists = $pdo->prepare(
        'SELECT id FROM rank_catalog WHERE country_code = ? AND branch = ? AND canonical_name = ? LIMIT 1'
    );
    $ins = $pdo->prepare(
        'INSERT INTO rank_catalog
            (country_code, branch, canonical_name, short_name, category, nato_code, us_equivalent,
             hierarchy_order, is_officer, is_nco, is_enlisted, is_active, verification_status,
             reference_source, reference_version, verified_at, verified_by, legacy_grade_code, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
    );

    $frArmy = [
        ['Soldat de 2e classe', 'Sdt 2', 'ENLISTED', 'OR-1', null, 10, 0, 0, 1, 'SD2'],
        ['Soldat de 1re classe', 'Sdt 1', 'ENLISTED', 'OR-2', null, 20, 0, 0, 1, 'SD1'],
        ['Caporal', 'Cpl', 'ENLISTED', 'OR-3', null, 30, 0, 0, 1, 'CPL'],
        ['Caporal-chef', 'Cch', 'ENLISTED', 'OR-4', null, 40, 0, 0, 1, 'CCH'],
        ['Sergent', 'Sgt', 'NCO', 'OR-5', null, 50, 0, 1, 0, 'SGT'],
        ['Sergent-chef', 'Sch', 'NCO', 'OR-6', null, 60, 0, 1, 0, 'SCH'],
        ['Adjudant', 'Adj', 'NCO', 'OR-7', null, 70, 0, 1, 0, 'ADJ'],
        ['Adjudant-chef', 'Adc', 'SENIOR_NCO', 'OR-8', null, 80, 0, 1, 0, 'ADC'],
        ['Major', 'Maj', 'SENIOR_NCO', 'OR-9', null, 90, 0, 1, 0, 'MAJ'],
        ['Sous-lieutenant', 'Slt', 'OFFICER', 'OF-1', 'O-1/O-2', 110, 1, 0, 0, 'SL'],
        ['Lieutenant', 'Lt', 'OFFICER', 'OF-1', 'O-1/O-2', 120, 1, 0, 0, 'LT'],
        ['Capitaine', 'Cne', 'OFFICER', 'OF-2', 'O-3', 130, 1, 0, 0, 'CNE'],
        ['Commandant', 'Cdt', 'OFFICER', 'OF-3', 'O-4', 140, 1, 0, 0, 'CDT'],
        ['Lieutenant-colonel', 'Lcl', 'OFFICER', 'OF-4', 'O-5', 150, 1, 0, 0, 'LCL'],
        ['Colonel', 'Col', 'OFFICER', 'OF-5', 'O-6', 160, 1, 0, 0, 'COL'],
        ['Général de brigade', 'Gén. bde', 'GENERAL_OFFICER', 'OF-6', 'O-7', 170, 1, 0, 0, 'GBR'],
        ['Général de division', 'Gén. div.', 'GENERAL_OFFICER', 'OF-7', 'O-8', 180, 1, 0, 0, 'GDV'],
        ['Général de corps d’armée', 'Gén. c. a.', 'GENERAL_OFFICER', 'OF-8', 'O-9', 190, 1, 0, 0, 'GCA'],
        ['Général d’armée', 'Gén. armée', 'GENERAL_OFFICER', 'OF-9', 'O-10', 200, 1, 0, 0, 'GAR'],
    ];
    $added = 0;
    foreach ($frArmy as $row) {
        [$name, $short, $cat, $nato, $us, $order, $off, $nco, $enl, $legacy] = $row;
        $exists->execute(['FR', 'ARMY', $name]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $ins->execute([
            'FR', 'ARMY', $name, $short, $cat, $nato, $us, $order, $off, $nco, $enl, 1, 'VERIFIED',
            'NATO STANAG / French Army customary OF/OR table', '2026-08-29', date('Y-m-d H:i:s'), 'system', $legacy,
        ]);
        ++$added;
    }

    $gendarmerie = [
        ['Gendarme', 'Gend.', 'ENLISTED', 20],
        ['Maréchal des logis-chef', 'MDC', 'NCO', 55],
        ['Adjudant', 'Adj', 'NCO', 70],
        ['Adjudant-chef', 'Adc', 'SENIOR_NCO', 80],
        ['Major', 'Maj', 'SENIOR_NCO', 90],
        ['Aspirant', 'Asp', 'OFFICER', 105],
        ['Sous-lieutenant', 'Slt', 'OFFICER', 110],
        ['Lieutenant', 'Lt', 'OFFICER', 120],
        ['Capitaine', 'Cne', 'OFFICER', 130],
        ['Chef d’escadron', 'Cen', 'OFFICER', 140],
        ['Lieutenant-colonel', 'Lcl', 'OFFICER', 150],
        ['Colonel', 'Col', 'OFFICER', 160],
        ['Général de brigade', 'Gén. bde', 'GENERAL_OFFICER', 170],
        ['Général de division', 'Gén. div.', 'GENERAL_OFFICER', 180],
    ];
    foreach ($gendarmerie as $row) {
        [$name, $short, $cat, $order] = $row;
        $exists->execute(['FR', 'GENDARMERIE', $name]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $off = in_array($cat, ['OFFICER', 'GENERAL_OFFICER'], true) ? 1 : 0;
        $nco = in_array($cat, ['NCO', 'SENIOR_NCO'], true) ? 1 : 0;
        $enl = $cat === 'ENLISTED' ? 1 : 0;
        $ins->execute([
            'FR', 'GENDARMERIE', $name, $short, $cat, null, null, $order, $off, $nco, $enl, 1, 'UNVERIFIED',
            'Pending official Gendarmerie OF/OR validation', '2026-08-29', null, null, null,
        ]);
        ++$added;
    }

    if ($added > 0) {
        echo "  [OK] rank_catalog seed ({$added} lignes)\n";
    }
}
