<?php

declare(strict_types=1);

/**
 * Référentiel de postes ORBAT (billets) : callsign org, états, occupation
 * (titulaire / intérim / adjoint), journal, snapshots, tags, notes, portraits.
 * Idempotent — s’appuie sur orbat_billets / orbat_billet_holders (lot 2).
 */
function run_orbat_billets_management_migration(PDO $pdo): void
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

    // --- Enrichissement orbat_billets ---
    if ($hasTable('orbat_billets')) {
        $billetCols = [
            'stable_code' => "ALTER TABLE orbat_billets ADD COLUMN stable_code CHAR(36) NULL AFTER id",
            'org_callsign' => "ALTER TABLE orbat_billets ADD COLUMN org_callsign VARCHAR(80) NULL AFTER title",
            'function_label' => "ALTER TABLE orbat_billets ADD COLUMN function_label VARCHAR(150) NULL AFTER org_callsign",
            'status' => "ALTER TABLE orbat_billets ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'active' AFTER is_active",
            'sort_order' => "ALTER TABLE orbat_billets ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER status",
            'is_key_post' => "ALTER TABLE orbat_billets ADD COLUMN is_key_post TINYINT(1) NOT NULL DEFAULT 0 AFTER is_critical",
            'key_post_kind' => "ALTER TABLE orbat_billets ADD COLUMN key_post_kind VARCHAR(40) NULL AFTER is_key_post",
            'notes' => "ALTER TABLE orbat_billets ADD COLUMN notes TEXT NULL AFTER key_post_kind",
            'effective_from' => "ALTER TABLE orbat_billets ADD COLUMN effective_from DATE NULL AFTER notes",
            'effective_to' => "ALTER TABLE orbat_billets ADD COLUMN effective_to DATE NULL AFTER effective_from",
            'archived_at' => "ALTER TABLE orbat_billets ADD COLUMN archived_at DATETIME NULL AFTER effective_to",
        ];
        foreach ($billetCols as $col => $sql) {
            if (!$hasColumn('orbat_billets', $col)) {
                $execTry($sql, 'orbat_billets.' . $col);
            }
        }
        // Remplir stable_code manquants
        if ($hasColumn('orbat_billets', 'stable_code')) {
            try {
                $rows = $pdo->query("SELECT id FROM orbat_billets WHERE stable_code IS NULL OR TRIM(stable_code) = ''")->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $upd = $pdo->prepare('UPDATE orbat_billets SET stable_code = ? WHERE id = ?');
                foreach ($rows as $r) {
                    $upd->execute([sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        random_int(0, 0xffff), random_int(0, 0xffff),
                        random_int(0, 0xffff),
                        random_int(0, 0x0fff) | 0x4000,
                        random_int(0, 0x3fff) | 0x8000,
                        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
                    ), (int) $r['id']]);
                }
            } catch (Throwable) {
            }
        }
    }

    // --- Enrichissement orbat_billet_holders ---
    if ($hasTable('orbat_billet_holders')) {
        $holderCols = [
            'occupancy_type' => "ALTER TABLE orbat_billet_holders ADD COLUMN occupancy_type VARCHAR(32) NOT NULL DEFAULT 'primary' AFTER holder_role",
            'movement_reason' => "ALTER TABLE orbat_billet_holders ADD COLUMN movement_reason VARCHAR(64) NULL AFTER occupancy_type",
            'notes' => "ALTER TABLE orbat_billet_holders ADD COLUMN notes VARCHAR(500) NULL AFTER movement_reason",
            'effective_at' => "ALTER TABLE orbat_billet_holders ADD COLUMN effective_at DATETIME NULL AFTER notes",
            'created_by' => "ALTER TABLE orbat_billet_holders ADD COLUMN created_by INT UNSIGNED NULL AFTER effective_at",
            'keeps_organic_billet' => "ALTER TABLE orbat_billet_holders ADD COLUMN keeps_organic_billet TINYINT(1) NOT NULL DEFAULT 0 AFTER created_by",
            'organic_billet_id' => "ALTER TABLE orbat_billet_holders ADD COLUMN organic_billet_id INT UNSIGNED NULL AFTER keeps_organic_billet",
        ];
        foreach ($holderCols as $col => $sql) {
            if (!$hasColumn('orbat_billet_holders', $col)) {
                $execTry($sql, 'orbat_billet_holders.' . $col);
            }
        }
        // Alignement holder_role PRIMARY → occupancy primary
        if ($hasColumn('orbat_billet_holders', 'occupancy_type')) {
            $execTry(
                "UPDATE orbat_billet_holders SET occupancy_type = CASE
                    WHEN holder_role = 'ALTERNATE' THEN 'alternate'
                    WHEN occupancy_type IS NULL OR occupancy_type = '' THEN 'primary'
                    ELSE occupancy_type
                END",
                'orbat_billet_holders.occupancy sync'
            );
        }
    }

    // --- Temp assignments : lien billet ---
    if ($hasTable('personnel_temporary_assignments')) {
        if (!$hasColumn('personnel_temporary_assignments', 'billet_id')) {
            $execTry(
                'ALTER TABLE personnel_temporary_assignments ADD COLUMN billet_id INT UNSIGNED NULL AFTER unit_id',
                'personnel_temporary_assignments.billet_id'
            );
        }
        if (!$hasColumn('personnel_temporary_assignments', 'organic_billet_id')) {
            $execTry(
                'ALTER TABLE personnel_temporary_assignments ADD COLUMN organic_billet_id INT UNSIGNED NULL AFTER billet_id',
                'personnel_temporary_assignments.organic_billet_id'
            );
        }
        if (!$hasColumn('personnel_temporary_assignments', 'movement_reason')) {
            $execTry(
                'ALTER TABLE personnel_temporary_assignments ADD COLUMN movement_reason VARCHAR(64) NULL AFTER does_not_change_grade',
                'personnel_temporary_assignments.movement_reason'
            );
        }
        if (!$hasColumn('personnel_temporary_assignments', 'effective_at')) {
            $execTry(
                'ALTER TABLE personnel_temporary_assignments ADD COLUMN effective_at DATETIME NULL AFTER ends_at',
                'personnel_temporary_assignments.effective_at'
            );
        }
    }

    // --- Units : callsign organisationnel ---
    if ($hasTable('units') && !$hasColumn('units', 'org_callsign')) {
        $execTry(
            'ALTER TABLE units ADD COLUMN org_callsign VARCHAR(80) NULL AFTER code',
            'units.org_callsign'
        );
    }

    // --- Journal de carrière / mouvements ---
    if (!$hasTable('personnel_career_journal')) {
        $execTry(
            "CREATE TABLE personnel_career_journal (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                event_type VARCHAR(64) NOT NULL,
                subject_type VARCHAR(32) NULL,
                subject_id INT UNSIGNED NULL,
                summary VARCHAR(500) NOT NULL,
                movement_reason VARCHAR(64) NULL,
                old_value VARCHAR(255) NULL,
                new_value VARCHAR(255) NULL,
                effective_at DATETIME NULL,
                actor_user_id INT UNSIGNED NULL,
                metadata_json JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_pcj_tenant_user (tenant_id, user_id, created_at),
                KEY idx_pcj_type (tenant_id, event_type, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'personnel_career_journal'
        );
    }

    // --- Snapshots ORBAT (versionnement) ---
    if (!$hasTable('orbat_structure_snapshots')) {
        $execTry(
            "CREATE TABLE orbat_structure_snapshots (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                label VARCHAR(200) NOT NULL,
                snapshot_kind VARCHAR(32) NOT NULL DEFAULT 'manual',
                effective_at DATETIME NOT NULL,
                payload_json LONGTEXT NOT NULL,
                notes TEXT NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_oss_tenant_eff (tenant_id, effective_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'orbat_structure_snapshots'
        );
    }

    // --- Tags organisationnels configurables ---
    if (!$hasTable('organization_tag_definitions')) {
        $execTry(
            "CREATE TABLE organization_tag_definitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                slug VARCHAR(80) NOT NULL,
                label VARCHAR(120) NOT NULL,
                color VARCHAR(16) NULL,
                applies_to VARCHAR(32) NOT NULL DEFAULT 'personnel',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_org_tag_slug (tenant_id, slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'organization_tag_definitions'
        );
    }
    if (!$hasTable('organization_tag_assignments')) {
        $execTry(
            "CREATE TABLE organization_tag_assignments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                tag_id INT UNSIGNED NOT NULL,
                subject_type VARCHAR(32) NOT NULL,
                subject_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_org_tag_assign (tenant_id, tag_id, subject_type, subject_id),
                KEY idx_ota_subject (tenant_id, subject_type, subject_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'organization_tag_assignments'
        );
    }

    // --- Notes admin structurées ---
    if (!$hasTable('personnel_admin_notes')) {
        $execTry(
            "CREATE TABLE personnel_admin_notes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                visibility_scope VARCHAR(32) NOT NULL DEFAULT 'command',
                category VARCHAR(64) NULL,
                body TEXT NOT NULL,
                created_by INT UNSIGNED NULL,
                updated_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                archived_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_pan_user (tenant_id, user_id, archived_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'personnel_admin_notes'
        );
    }

    // --- Historique portraits ---
    if (!$hasTable('personnel_portrait_history')) {
        $execTry(
            "CREATE TABLE personnel_portrait_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                path VARCHAR(512) NOT NULL,
                is_current TINYINT(1) NOT NULL DEFAULT 0,
                uploaded_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_pph_user (tenant_id, user_id, is_current)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'personnel_portrait_history'
        );
    }

    // --- Documents dossier (si absent) ---
    if (!$hasTable('personnel_internal_documents')) {
        $execTry(
            "CREATE TABLE personnel_internal_documents (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                title VARCHAR(200) NOT NULL,
                category VARCHAR(80) NULL,
                visibility_scope VARCHAR(32) NOT NULL DEFAULT 'staff',
                file_path VARCHAR(512) NOT NULL,
                mime_type VARCHAR(120) NULL,
                file_size INT UNSIGNED NULL,
                uploaded_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                archived_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_pid_user (tenant_id, user_id, archived_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'personnel_internal_documents'
        );
    }

    // --- Situations administratives configurables ---
    if (!$hasTable('personnel_admin_status_definitions')) {
        $execTry(
            "CREATE TABLE personnel_admin_status_definitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(40) NOT NULL,
                label VARCHAR(120) NOT NULL,
                is_available TINYINT(1) NOT NULL DEFAULT 1,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_pasd_code (tenant_id, code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'personnel_admin_status_definitions'
        );
        // Seeds par défaut
        try {
            $ins = $pdo->prepare(
                'INSERT IGNORE INTO personnel_admin_status_definitions (tenant_id, code, label, is_available, sort_order)
                 SELECT id, ?, ?, ?, ? FROM tenants'
            );
            $defaults = [
                ['active', 'Actif', 1, 10],
                ['unavailable', 'Indisponible', 0, 20],
                ['absence', 'Absence', 0, 30],
                ['training', 'Formation', 0, 40],
                ['detached', 'Détaché', 0, 50],
                ['reserve', 'Réserve', 0, 60],
            ];
            foreach ($defaults as [$code, $label, $avail, $ord]) {
                $ins->execute([$code, $label, $avail, $ord]);
            }
        } catch (Throwable) {
        }
    }

    if ($hasTable('personnel_profiles') && !$hasColumn('personnel_profiles', 'admin_situation')) {
        $execTry(
            "ALTER TABLE personnel_profiles ADD COLUMN admin_situation VARCHAR(40) NOT NULL DEFAULT 'active' AFTER visibility_level",
            'personnel_profiles.admin_situation'
        );
    }

    // --- Motifs de mouvement configurables ---
    if (!$hasTable('organization_movement_reasons')) {
        $execTry(
            "CREATE TABLE organization_movement_reasons (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(40) NOT NULL,
                label VARCHAR(120) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_omr_code (tenant_id, code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'organization_movement_reasons'
        );
        try {
            $ins = $pdo->prepare(
                'INSERT IGNORE INTO organization_movement_reasons (tenant_id, code, label, sort_order)
                 SELECT id, ?, ?, ? FROM tenants'
            );
            foreach ([
                ['mutation', 'Mutation', 10],
                ['reorganization', 'Réorganisation', 20],
                ['promotion', 'Promotion', 30],
                ['temporary', 'Affectation temporaire', 40],
                ['acting', 'Intérim / suppléance', 50],
                ['other', 'Autre', 90],
            ] as [$c, $l, $o]) {
                $ins->execute([$c, $l, $o]);
            }
        } catch (Throwable) {
        }
    }

    echo "  [OK] orbat_billets_management_migration\n";
}
