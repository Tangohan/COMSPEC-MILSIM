<?php

declare(strict_types=1);

/**
 * Référentiel qualifications ATHENA — définition ≠ attribution.
 * Enrichit personnel_qualification_definitions + personnel_qualifications
 * et crée les tables satellites (catégories, types, niveaux, émetteurs, PDF, etc.).
 */
function run_qualification_referentiel_migration(PDO $pdo): void
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
    $addColumn = static function (string $table, string $column, string $ddl) use ($pdo, $hasColumn): void {
        if ($hasColumn($table, $column)) {
            return;
        }
        try {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$ddl}");
        } catch (Throwable) {
        }
    };
    $ensurePermission = static function (string $code, string $name, string $description) use ($pdo, $hasTable): void {
        if (!$hasTable('permissions')) {
            return;
        }
        $st = $pdo->prepare('SELECT id FROM permissions WHERE code = ? LIMIT 1');
        $st->execute([$code]);
        if ($st->fetchColumn()) {
            return;
        }
        try {
            $ins = $pdo->prepare(
                'INSERT INTO permissions (code, name, description, created_at) VALUES (?, ?, ?, NOW())'
            );
            $ins->execute([$code, $name, $description]);
        } catch (Throwable) {
            try {
                $ins = $pdo->prepare(
                    'INSERT INTO permissions (code, name, description) VALUES (?, ?, ?)'
                );
                $ins->execute([$code, $name, $description]);
            } catch (Throwable) {
            }
        }
    };

    /* ---------- Tables satellites ---------- */

    if (!$hasTable('qualification_categories')) {
        $pdo->exec(
            "CREATE TABLE qualification_categories (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                name VARCHAR(150) NOT NULL,
                parent_category_id INT UNSIGNED DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_qual_cat_tenant (tenant_id, sort_order),
                KEY idx_qual_cat_parent (parent_category_id),
                CONSTRAINT qual_cat_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('qualification_types')) {
        $pdo->exec(
            "CREATE TABLE qualification_types (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                code VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_qual_type_tenant_code (tenant_id, code),
                CONSTRAINT qual_type_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('qualification_issuers')) {
        $pdo->exec(
            "CREATE TABLE qualification_issuers (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                name VARCHAR(200) NOT NULL,
                short_name VARCHAR(80) DEFAULT NULL,
                issuer_kind VARCHAR(32) NOT NULL DEFAULT 'unit',
                parent_issuer_id INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_qual_issuer_tenant (tenant_id, name),
                KEY idx_qual_issuer_parent (parent_issuer_id),
                CONSTRAINT qual_issuer_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('qualification_certificate_templates')) {
        $pdo->exec(
            "CREATE TABLE qualification_certificate_templates (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED DEFAULT NULL,
                code VARCHAR(64) NOT NULL,
                name VARCHAR(150) NOT NULL,
                layout_json JSON DEFAULT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_qual_cert_tpl_tenant_code (tenant_id, code),
                KEY idx_qual_cert_tpl_system (is_system, code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /* Seed gabarits système (tenant_id NULL) */
    $seedTpl = $pdo->prepare(
        'INSERT INTO qualification_certificate_templates (tenant_id, code, name, layout_json, is_default, is_system)
         SELECT NULL, ?, ?, ?, ?, 1 FROM DUAL
         WHERE NOT EXISTS (
           SELECT 1 FROM qualification_certificate_templates WHERE tenant_id IS NULL AND code = ?
         )'
    );
    $seedTpl->execute([
        'classique',
        'Classique',
        json_encode(['layout' => 'classique', 'primary_hex' => '#0f172a', 'accent_hex' => '#334155'], JSON_UNESCAPED_UNICODE),
        1,
        'classique',
    ]);
    $seedTpl->execute([
        'moderne',
        'Moderne',
        json_encode(['layout' => 'moderne', 'primary_hex' => '#0f172a', 'accent_hex' => '#059669'], JSON_UNESCAPED_UNICODE),
        0,
        'moderne',
    ]);

    if (!$hasTable('qualification_levels')) {
        $pdo->exec(
            "CREATE TABLE qualification_levels (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                qualification_id INT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                short_name VARCHAR(40) DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                description TEXT DEFAULT NULL,
                previous_level_id INT UNSIGNED DEFAULT NULL,
                badge_media_path VARCHAR(500) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_qual_level_qual (qualification_id, sort_order),
                KEY idx_qual_level_tenant (tenant_id),
                CONSTRAINT qual_level_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('qualification_versions')) {
        $pdo->exec(
            "CREATE TABLE qualification_versions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                qualification_id INT UNSIGNED NOT NULL,
                version_number INT UNSIGNED NOT NULL DEFAULT 1,
                effective_from DATE NOT NULL,
                changelog TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_qual_version (qualification_id, version_number),
                KEY idx_qual_version_tenant (tenant_id),
                CONSTRAINT qual_version_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('qualification_prerequisites')) {
        $pdo->exec(
            "CREATE TABLE qualification_prerequisites (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                qualification_id INT UNSIGNED NOT NULL,
                required_qualification_id INT UNSIGNED NOT NULL,
                minimum_level_id INT UNSIGNED DEFAULT NULL,
                requirement_type VARCHAR(32) NOT NULL DEFAULT 'obtention',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_qual_prereq (qualification_id, required_qualification_id, requirement_type),
                KEY idx_qual_prereq_tenant (tenant_id),
                CONSTRAINT qual_prereq_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('qualification_definition_equivalences')) {
        $pdo->exec(
            "CREATE TABLE qualification_definition_equivalences (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                qualification_id INT UNSIGNED NOT NULL,
                equivalent_qualification_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_qual_def_equiv (qualification_id, equivalent_qualification_id),
                CONSTRAINT qual_def_equiv_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('qualification_history')) {
        $pdo->exec(
            "CREATE TABLE qualification_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                personnel_qualification_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(64) NOT NULL,
                event_date DATETIME NOT NULL,
                performed_by INT UNSIGNED DEFAULT NULL,
                details TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_qual_hist_award (personnel_qualification_id, event_date),
                KEY idx_qual_hist_tenant (tenant_id),
                CONSTRAINT qual_hist_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('qualification_custom_fields')) {
        $pdo->exec(
            "CREATE TABLE qualification_custom_fields (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                qualification_id INT UNSIGNED NOT NULL,
                name VARCHAR(150) NOT NULL,
                code VARCHAR(64) NOT NULL,
                field_type VARCHAR(32) NOT NULL DEFAULT 'text_short',
                is_required TINYINT(1) NOT NULL DEFAULT 0,
                default_value TEXT DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                visibility VARCHAR(32) NOT NULL DEFAULT 'normal',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_qual_cf_code (qualification_id, code),
                CONSTRAINT qual_cf_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_qualification_custom_values')) {
        $pdo->exec(
            "CREATE TABLE personnel_qualification_custom_values (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                personnel_qualification_id BIGINT UNSIGNED NOT NULL,
                custom_field_id INT UNSIGNED NOT NULL,
                value TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_pq_cf_val (personnel_qualification_id, custom_field_id),
                CONSTRAINT pq_cf_val_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('qualification_award_panels')) {
        $pdo->exec(
            "CREATE TABLE qualification_award_panels (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                personnel_qualification_id BIGINT UNSIGNED NOT NULL,
                panel_member_user_id INT UNSIGNED NOT NULL,
                role_in_panel VARCHAR(32) NOT NULL DEFAULT 'evaluator',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_qual_panel_member (personnel_qualification_id, panel_member_user_id),
                CONSTRAINT qual_panel_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('post_qualification_requirements')) {
        $pdo->exec(
            "CREATE TABLE post_qualification_requirements (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                post_id INT UNSIGNED NOT NULL,
                qualification_id INT UNSIGNED NOT NULL,
                minimum_level_id INT UNSIGNED DEFAULT NULL,
                requirement_type VARCHAR(32) NOT NULL DEFAULT 'required',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_post_qual_req (post_id, qualification_id),
                KEY idx_post_qual_req_tenant (tenant_id),
                CONSTRAINT post_qual_req_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('qualification_grants_permission')) {
        $pdo->exec(
            "CREATE TABLE qualification_grants_permission (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                qualification_id INT UNSIGNED NOT NULL,
                qualification_level_id INT UNSIGNED DEFAULT NULL,
                permission_code VARCHAR(120) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_qual_grant_perm (qualification_id, qualification_level_id, permission_code),
                KEY idx_qual_grant_tenant (tenant_id),
                CONSTRAINT qual_grant_perm_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('qualification_certificate_sequences')) {
        $pdo->exec(
            "CREATE TABLE qualification_certificate_sequences (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                year_key SMALLINT UNSIGNED NOT NULL,
                last_sequence INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_qual_cert_seq (tenant_id, year_key),
                CONSTRAINT qual_cert_seq_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /* ---------- Enrichir personnel_qualification_definitions ---------- */

    if ($hasTable('personnel_qualification_definitions')) {
        $addColumn('personnel_qualification_definitions', 'short_name', 'short_name VARCHAR(40) DEFAULT NULL AFTER name');
        $addColumn('personnel_qualification_definitions', 'description', 'description TEXT DEFAULT NULL AFTER short_name');
        $addColumn('personnel_qualification_definitions', 'category_id', 'category_id INT UNSIGNED DEFAULT NULL AFTER description');
        $addColumn('personnel_qualification_definitions', 'type_id', 'type_id INT UNSIGNED DEFAULT NULL AFTER category_id');
        $addColumn('personnel_qualification_definitions', 'uses_levels', 'uses_levels TINYINT(1) NOT NULL DEFAULT 0 AFTER type_id');
        $addColumn('personnel_qualification_definitions', 'is_permanent', 'is_permanent TINYINT(1) NOT NULL DEFAULT 0 AFTER uses_levels');
        $addColumn('personnel_qualification_definitions', 'default_validity_months', 'default_validity_months INT UNSIGNED DEFAULT NULL AFTER is_permanent');
        $addColumn('personnel_qualification_definitions', 'alert_before_expiry_days', 'alert_before_expiry_days INT UNSIGNED DEFAULT NULL AFTER default_validity_months');
        $addColumn('personnel_qualification_definitions', 'grace_period_days', 'grace_period_days INT UNSIGNED DEFAULT NULL AFTER alert_before_expiry_days');
        $addColumn('personnel_qualification_definitions', 'enforce_level_progression', 'enforce_level_progression TINYINT(1) NOT NULL DEFAULT 0 AFTER grace_period_days');
        $addColumn('personnel_qualification_definitions', 'requires_panel', 'requires_panel TINYINT(1) NOT NULL DEFAULT 0 AFTER enforce_level_progression');
        $addColumn('personnel_qualification_definitions', 'qualification_scope', "qualification_scope VARCHAR(16) NOT NULL DEFAULT 'global' AFTER requires_panel");
        $addColumn('personnel_qualification_definitions', 'badge_media_path', 'badge_media_path VARCHAR(500) DEFAULT NULL AFTER qualification_scope');
        $addColumn('personnel_qualification_definitions', 'certificate_template_id', 'certificate_template_id INT UNSIGNED DEFAULT NULL AFTER badge_media_path');
        $addColumn('personnel_qualification_definitions', 'archived_at', 'archived_at DATETIME DEFAULT NULL AFTER certificate_template_id');
        $addColumn('personnel_qualification_definitions', 'created_by', 'created_by INT UNSIGNED DEFAULT NULL AFTER archived_at');
        $addColumn('personnel_qualification_definitions', 'updated_by', 'updated_by INT UNSIGNED DEFAULT NULL AFTER created_by');
        $addColumn('personnel_qualification_definitions', 'certificate_number_format', "certificate_number_format VARCHAR(120) DEFAULT NULL AFTER updated_by");
    }

    /* ---------- Enrichir personnel_qualifications ---------- */

    if ($hasTable('personnel_qualifications')) {
        $addColumn('personnel_qualifications', 'qualification_level_id', 'qualification_level_id INT UNSIGNED DEFAULT NULL AFTER definition_id');
        $addColumn('personnel_qualifications', 'issuer_id', 'issuer_id INT UNSIGNED DEFAULT NULL AFTER qualification_level_id');
        $addColumn('personnel_qualifications', 'certificate_number', 'certificate_number VARCHAR(120) DEFAULT NULL AFTER issuer_id');
        $addColumn('personnel_qualifications', 'certificate_document_path', 'certificate_document_path VARCHAR(500) DEFAULT NULL AFTER certificate_number');
        $addColumn('personnel_qualifications', 'attempt_number', 'attempt_number INT UNSIGNED NOT NULL DEFAULT 1 AFTER certificate_document_path');
        $addColumn('personnel_qualifications', 'renewal_of_id', 'renewal_of_id BIGINT UNSIGNED DEFAULT NULL AFTER attempt_number');
        $addColumn('personnel_qualifications', 'reference', 'reference VARCHAR(150) DEFAULT NULL AFTER renewal_of_id');
        $addColumn('personnel_qualifications', 'notes', 'notes TEXT DEFAULT NULL AFTER reference');
        $addColumn('personnel_qualifications', 'is_primary', 'is_primary TINYINT(1) NOT NULL DEFAULT 0 AFTER notes');
        $addColumn('personnel_qualifications', 'revoked_at', 'revoked_at DATETIME DEFAULT NULL AFTER is_primary');
        $addColumn('personnel_qualifications', 'revoked_by', 'revoked_by INT UNSIGNED DEFAULT NULL AFTER revoked_at');
        $addColumn('personnel_qualifications', 'revocation_reason', 'revocation_reason TEXT DEFAULT NULL AFTER revoked_by');
        $addColumn('personnel_qualifications', 'qualification_version_id', 'qualification_version_id INT UNSIGNED DEFAULT NULL AFTER revocation_reason');
        $addColumn('personnel_qualifications', 'is_retrospective', 'is_retrospective TINYINT(1) NOT NULL DEFAULT 0 AFTER qualification_version_id');
        $addColumn('personnel_qualifications', 'created_by', 'created_by INT UNSIGNED DEFAULT NULL AFTER is_retrospective');
        $addColumn('personnel_qualifications', 'updated_by', 'updated_by INT UNSIGNED DEFAULT NULL AFTER created_by');
        $addColumn('personnel_qualifications', 'admin_status', "admin_status VARCHAR(32) DEFAULT NULL AFTER status");

        /* Migrer legacy status → admin_status (une fois) */
        try {
            $pdo->exec(
                "UPDATE personnel_qualifications SET admin_status = CASE
                    WHEN status IN ('valid','expiring','expired') THEN 'obtained'
                    WHEN status = 'in_progress' THEN 'in_training'
                    WHEN status IN ('candidate','candidat') THEN 'candidate'
                    WHEN status IN ('in_training','en_formation') THEN 'in_training'
                    WHEN status IN ('in_evaluation','en_evaluation') THEN 'in_evaluation'
                    WHEN status IN ('obtained','obtenue') THEN 'obtained'
                    WHEN status IN ('failed','echouee') THEN 'failed'
                    WHEN status IN ('suspended','suspendue') THEN 'suspended'
                    WHEN status IN ('revoked','retiree','retirée') THEN 'revoked'
                    ELSE COALESCE(admin_status, 'obtained')
                 END
                 WHERE admin_status IS NULL OR admin_status = ''"
            );
        } catch (Throwable) {
        }
    }

    /* ---------- Permissions ---------- */
    $ensurePermission(
        'personnel.qualification.manage',
        'Gérer le référentiel de qualifications',
        'Créer, modifier et archiver les qualifications, catégories, types, niveaux et émetteurs.'
    );
    $ensurePermission(
        'personnel.qualification.grant',
        'Attribuer des qualifications',
        'Attribuer, renouveler, suspendre ou retirer une qualification à un membre.'
    );

    /* ---------- Seed types par tenant existant ---------- */
    if ($hasTable('tenants') && $hasTable('qualification_types')) {
        $tenants = $pdo->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $types = [
            ['QUALIFICATION', 'Qualification'],
            ['CERTIFICATION', 'Certification'],
            ['HABILITATION', 'Habilitation'],
            ['COMPETENCE', 'Compétence'],
            ['SPECIALISATION', 'Spécialisation'],
        ];
        $insType = $pdo->prepare(
            'INSERT INTO qualification_types (tenant_id, name, code)
             SELECT ?, ?, ? FROM DUAL
             WHERE NOT EXISTS (
               SELECT 1 FROM qualification_types WHERE tenant_id = ? AND code = ?
             )'
        );
        foreach ($tenants as $tid) {
            $tid = (int) $tid;
            foreach ($types as [$code, $name]) {
                $insType->execute([$tid, $name, $code, $tid, $code]);
            }
        }
    }

    /* Lien FK levels → definitions (après création des deux) */
    if ($hasTable('qualification_levels') && $hasTable('personnel_qualification_definitions')) {
        try {
            $pdo->exec(
                'ALTER TABLE qualification_levels
                 ADD CONSTRAINT qual_level_def_fk
                 FOREIGN KEY (qualification_id) REFERENCES personnel_qualification_definitions (id)
                 ON DELETE CASCADE ON UPDATE CASCADE'
            );
        } catch (Throwable) {
        }
    }
}
