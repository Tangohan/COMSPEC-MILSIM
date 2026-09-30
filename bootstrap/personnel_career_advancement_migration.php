<?php

declare(strict_types=1);

/**
 * Avancement de grade (échelle communauté + historique + campagnes au choix),
 * décorations, dotation nominative.
 * Idempotent — jamais de suppression physique d’historique.
 */
function run_personnel_career_advancement_migration(PDO $pdo): void
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
                $ins = $pdo->prepare('INSERT INTO permissions (code, name, description) VALUES (?, ?, ?)');
                $ins->execute([$code, $name, $description]);
            } catch (Throwable) {
            }
        }
    };

    if (!$hasTable('grade_filiere_definitions')) {
        $pdo->exec(
            "CREATE TABLE grade_filiere_definitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(40) NOT NULL,
                label VARCHAR(150) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_grade_filiere_tenant_code (tenant_id, code),
                KEY idx_grade_filiere_tenant (tenant_id, sort_order),
                CONSTRAINT grade_filiere_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('grade_definitions')) {
        $pdo->exec(
            "CREATE TABLE grade_definitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(40) NOT NULL,
                label VARCHAR(150) NOT NULL,
                short_label VARCHAR(40) DEFAULT NULL,
                filiere_id INT UNSIGNED DEFAULT NULL,
                rank_order INT NOT NULL DEFAULT 0,
                advancement_seniority_enabled TINYINT(1) NOT NULL DEFAULT 0,
                advancement_choice_enabled TINYINT(1) NOT NULL DEFAULT 0,
                min_time_in_previous_grade_months INT UNSIGNED DEFAULT NULL,
                required_qualification_id INT UNSIGNED DEFAULT NULL,
                required_qualification_level_id INT UNSIGNED DEFAULT NULL,
                source_catalog_grade_id BIGINT UNSIGNED DEFAULT NULL,
                template_key VARCHAR(40) DEFAULT NULL,
                archived_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_grade_def_tenant_code (tenant_id, code),
                KEY idx_grade_def_tenant_order (tenant_id, rank_order),
                KEY idx_grade_def_filiere (filiere_id),
                CONSTRAINT grade_def_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT grade_def_filiere_fk FOREIGN KEY (filiere_id) REFERENCES grade_filiere_definitions (id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } else {
        $adds = [
            'source_catalog_grade_id' => 'source_catalog_grade_id BIGINT UNSIGNED DEFAULT NULL AFTER required_qualification_level_id',
            'template_key' => 'template_key VARCHAR(40) DEFAULT NULL AFTER source_catalog_grade_id',
        ];
        foreach ($adds as $col => $ddl) {
            if (!$hasColumn('grade_definitions', $col)) {
                try {
                    $pdo->exec('ALTER TABLE grade_definitions ADD COLUMN ' . $ddl);
                } catch (Throwable) {
                }
            }
        }
    }

    if (!$hasTable('personnel_grade_history')) {
        $pdo->exec(
            "CREATE TABLE personnel_grade_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                personnel_id INT UNSIGNED NOT NULL,
                grade_id INT UNSIGNED NOT NULL,
                obtained_at DATE NOT NULL,
                obtained_via VARCHAR(24) NOT NULL DEFAULT 'initial',
                candidacy_id INT UNSIGNED DEFAULT NULL,
                ends_at DATE DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_pgh_current (tenant_id, personnel_id, ends_at),
                KEY idx_pgh_grade (grade_id),
                KEY idx_pgh_candidacy (candidacy_id),
                CONSTRAINT pgh_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT pgh_user_fk FOREIGN KEY (personnel_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT pgh_grade_fk FOREIGN KEY (grade_id) REFERENCES grade_definitions (id) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('advancement_campaigns')) {
        $pdo->exec(
            "CREATE TABLE advancement_campaigns (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                grade_id INT UNSIGNED NOT NULL,
                filiere_id INT UNSIGNED DEFAULT NULL,
                year INT NOT NULL,
                opens_at DATE DEFAULT NULL,
                closes_at DATE DEFAULT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'open',
                quota_slots INT UNSIGNED DEFAULT NULL,
                published_at DATE DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_adv_camp_tenant (tenant_id, year, status),
                KEY idx_adv_camp_grade (grade_id),
                CONSTRAINT adv_camp_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT adv_camp_grade_fk FOREIGN KEY (grade_id) REFERENCES grade_definitions (id) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('advancement_candidacies')) {
        $pdo->exec(
            "CREATE TABLE advancement_candidacies (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                campaign_id INT UNSIGNED NOT NULL,
                personnel_id INT UNSIGNED NOT NULL,
                volunteered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                is_eligible TINYINT(1) NOT NULL DEFAULT 0,
                eligibility_reason TEXT DEFAULT NULL,
                preference_rank INT UNSIGNED DEFAULT NULL,
                commission_opinion VARCHAR(24) DEFAULT NULL,
                decision VARCHAR(24) DEFAULT NULL,
                decided_at DATE DEFAULT NULL,
                mobility_requested TINYINT(1) NOT NULL DEFAULT 0,
                requested_billet_id INT UNSIGNED DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_adv_cand_campaign_user (campaign_id, personnel_id),
                KEY idx_adv_cand_user (personnel_id),
                KEY idx_adv_cand_rank (campaign_id, preference_rank),
                CONSTRAINT adv_cand_campaign_fk FOREIGN KEY (campaign_id) REFERENCES advancement_campaigns (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT adv_cand_user_fk FOREIGN KEY (personnel_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('advancement_commissions')) {
        $pdo->exec(
            "CREATE TABLE advancement_commissions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                campaign_id INT UNSIGNED NOT NULL,
                meeting_date DATE DEFAULT NULL,
                minutes_document_id INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_adv_commission_campaign (campaign_id),
                CONSTRAINT adv_comm_campaign_fk FOREIGN KEY (campaign_id) REFERENCES advancement_campaigns (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('advancement_commission_members')) {
        $pdo->exec(
            "CREATE TABLE advancement_commission_members (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                commission_id INT UNSIGNED NOT NULL,
                personnel_id INT UNSIGNED NOT NULL,
                role VARCHAR(24) NOT NULL DEFAULT 'titular',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_adv_comm_member (commission_id, personnel_id),
                CONSTRAINT adv_cmem_commission_fk FOREIGN KEY (commission_id) REFERENCES advancement_commissions (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT adv_cmem_user_fk FOREIGN KEY (personnel_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('award_definitions')) {
        $pdo->exec(
            "CREATE TABLE award_definitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(40) NOT NULL,
                name VARCHAR(180) NOT NULL,
                decoration_grade VARCHAR(80) DEFAULT NULL,
                award_criterion TEXT DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                archived_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_award_def_tenant_code (tenant_id, code),
                KEY idx_award_def_tenant (tenant_id, sort_order),
                CONSTRAINT award_def_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_awards')) {
        $pdo->exec(
            "CREATE TABLE personnel_awards (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                personnel_id INT UNSIGNED NOT NULL,
                definition_id INT UNSIGNED NOT NULL,
                citation_text TEXT DEFAULT NULL,
                authority VARCHAR(180) DEFAULT NULL,
                awarded_at DATE NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_personnel_awards_user (tenant_id, personnel_id, awarded_at),
                KEY idx_personnel_awards_def (definition_id),
                CONSTRAINT paward_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT paward_user_fk FOREIGN KEY (personnel_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT paward_def_fk FOREIGN KEY (definition_id) REFERENCES award_definitions (id) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('equipment_item_definitions')) {
        $pdo->exec(
            "CREATE TABLE equipment_item_definitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(40) NOT NULL,
                name VARCHAR(180) NOT NULL,
                category VARCHAR(80) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                archived_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_equip_def_tenant_code (tenant_id, code),
                KEY idx_equip_def_tenant (tenant_id, name),
                CONSTRAINT equip_def_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_equipment_assignments')) {
        $pdo->exec(
            "CREATE TABLE personnel_equipment_assignments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                personnel_id INT UNSIGNED NOT NULL,
                definition_id INT UNSIGNED NOT NULL,
                serial_number VARCHAR(80) DEFAULT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'issued',
                assigned_at DATE NOT NULL,
                returned_at DATE DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_equip_asg_user (tenant_id, personnel_id, status),
                KEY idx_equip_asg_serial (tenant_id, serial_number),
                CONSTRAINT equip_asg_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT equip_asg_user_fk FOREIGN KEY (personnel_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT equip_asg_def_fk FOREIGN KEY (definition_id) REFERENCES equipment_item_definitions (id) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_equipment_assignment_history')) {
        $pdo->exec(
            "CREATE TABLE personnel_equipment_assignment_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                assignment_id BIGINT UNSIGNED NOT NULL,
                event VARCHAR(32) NOT NULL,
                from_status VARCHAR(24) DEFAULT NULL,
                to_status VARCHAR(24) DEFAULT NULL,
                personnel_id INT UNSIGNED DEFAULT NULL,
                notes VARCHAR(500) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_equip_hist_asg (assignment_id, created_at),
                CONSTRAINT equip_hist_asg_fk FOREIGN KEY (assignment_id) REFERENCES personnel_equipment_assignments (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    $ensurePermission(
        'personnel.advancement.manage',
        'Gérer l’avancement de grade',
        'Campagnes, commissions et publication du tableau d’avancement.'
    );
    $ensurePermission(
        'personnel.awards.manage',
        'Gérer les décorations',
        'Référentiel et attributions de décorations / citations.'
    );
    $ensurePermission(
        'personnel.equipment.manage',
        'Gérer la dotation',
        'Référentiel matériel et attributions nominatives.'
    );

    echo "  [OK] personnel_career_advancement (grades communauté, campagnes, décorations, dotation)\n";
}
