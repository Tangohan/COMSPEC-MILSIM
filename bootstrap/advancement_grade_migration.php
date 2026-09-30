<?php

declare(strict_types=1);

/**
 * Avancement de grade ATHENA — échelle scopée par communauté, historique non écrasable,
 * campagnes au choix (candidature, commission, publication).
 */
function run_advancement_grade_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('grade_filiere_definitions')) {
        $pdo->exec(
            "CREATE TABLE grade_filiere_definitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(64) NOT NULL,
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
                code VARCHAR(64) NOT NULL,
                label VARCHAR(150) NOT NULL,
                short_label VARCHAR(40) DEFAULT NULL,
                filiere_id INT UNSIGNED DEFAULT NULL,
                rank_order INT NOT NULL DEFAULT 0,
                advancement_seniority_enabled TINYINT(1) NOT NULL DEFAULT 0,
                advancement_choice_enabled TINYINT(1) NOT NULL DEFAULT 0,
                min_time_in_previous_grade_months INT DEFAULT NULL,
                required_qualification_id INT UNSIGNED DEFAULT NULL,
                required_qualification_level_id INT UNSIGNED DEFAULT NULL,
                archived_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_grade_def_tenant_code (tenant_id, code),
                KEY idx_grade_def_tenant_order (tenant_id, filiere_id, rank_order),
                KEY idx_grade_def_qual (required_qualification_id),
                CONSTRAINT grade_def_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT grade_def_filiere_fk FOREIGN KEY (filiere_id) REFERENCES grade_filiere_definitions (id) ON DELETE SET NULL ON UPDATE CASCADE
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
                opens_at DATE NOT NULL,
                closes_at DATE NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'ouverte',
                quota_slots INT DEFAULT NULL,
                published_at DATE DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_adv_campaign (tenant_id, grade_id, year),
                KEY idx_adv_campaign_status (tenant_id, status, year),
                CONSTRAINT adv_campaign_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT adv_campaign_grade_fk FOREIGN KEY (grade_id) REFERENCES grade_definitions (id) ON DELETE RESTRICT ON UPDATE CASCADE
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
                preference_rank INT DEFAULT NULL,
                commission_opinion VARCHAR(32) DEFAULT NULL,
                decision VARCHAR(32) DEFAULT NULL,
                decided_at DATE DEFAULT NULL,
                mobility_requested TINYINT(1) NOT NULL DEFAULT 0,
                requested_billet_id INT UNSIGNED DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                exceptional_override TINYINT(1) NOT NULL DEFAULT 0,
                exceptional_reason TEXT DEFAULT NULL,
                exceptional_by INT UNSIGNED DEFAULT NULL,
                exceptional_at DATETIME DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_adv_candidacy (campaign_id, personnel_id),
                KEY idx_adv_candidacy_personnel (personnel_id),
                CONSTRAINT adv_candidacy_campaign_fk FOREIGN KEY (campaign_id) REFERENCES advancement_campaigns (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT adv_candidacy_personnel_fk FOREIGN KEY (personnel_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_grade_history')) {
        $pdo->exec(
            "CREATE TABLE personnel_grade_history (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                personnel_id INT UNSIGNED NOT NULL,
                grade_id INT UNSIGNED NOT NULL,
                obtained_at DATE NOT NULL,
                obtained_via VARCHAR(32) NOT NULL,
                candidacy_id INT UNSIGNED DEFAULT NULL,
                ends_at DATE DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_grade_hist_personnel (personnel_id, ends_at, obtained_at),
                KEY idx_grade_hist_grade (grade_id),
                KEY idx_grade_hist_candidacy (candidacy_id),
                CONSTRAINT grade_hist_personnel_fk FOREIGN KEY (personnel_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT grade_hist_grade_fk FOREIGN KEY (grade_id) REFERENCES grade_definitions (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT grade_hist_candidacy_fk FOREIGN KEY (candidacy_id) REFERENCES advancement_candidacies (id) ON DELETE SET NULL ON UPDATE CASCADE
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
                PRIMARY KEY (id),
                UNIQUE KEY uniq_adv_commission_campaign (campaign_id),
                CONSTRAINT adv_commission_campaign_fk FOREIGN KEY (campaign_id) REFERENCES advancement_campaigns (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('advancement_commission_members')) {
        $pdo->exec(
            "CREATE TABLE advancement_commission_members (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                commission_id INT UNSIGNED NOT NULL,
                personnel_id INT UNSIGNED NOT NULL,
                role VARCHAR(32) NOT NULL DEFAULT 'titulaire',
                PRIMARY KEY (id),
                UNIQUE KEY uniq_adv_commission_member (commission_id, personnel_id),
                CONSTRAINT adv_comm_member_commission_fk FOREIGN KEY (commission_id) REFERENCES advancement_commissions (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT adv_comm_member_personnel_fk FOREIGN KEY (personnel_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };
    if ($hasTable('advancement_candidacies') && !$hasColumn('advancement_candidacies', 'exceptional_override')) {
        $pdo->exec(
            'ALTER TABLE advancement_candidacies
             ADD COLUMN exceptional_override TINYINT(1) NOT NULL DEFAULT 0,
             ADD COLUMN exceptional_reason TEXT DEFAULT NULL,
             ADD COLUMN exceptional_by INT UNSIGNED DEFAULT NULL,
             ADD COLUMN exceptional_at DATETIME DEFAULT NULL'
        );
    }
}
