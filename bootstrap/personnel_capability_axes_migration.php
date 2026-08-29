<?php

declare(strict_types=1);

/**
 * Lot 2 — axes RH séparés + currency + gouvernance avancée.
 * Ne fusionne jamais grade, fonction, qualification et capacité opérationnelle.
 */
function run_personnel_capability_axes_migration(PDO $pdo): void
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

    if (!$hasTable('personnel_qualification_definitions')) {
        $pdo->exec(
            "CREATE TABLE personnel_qualification_definitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(64) NOT NULL,
                name VARCHAR(150) NOT NULL,
                category VARCHAR(80) DEFAULT NULL,
                level_rank INT NOT NULL DEFAULT 1,
                parent_definition_id INT UNSIGNED DEFAULT NULL,
                validity_days INT UNSIGNED DEFAULT NULL,
                currency_days INT UNSIGNED DEFAULT NULL,
                requires_exam TINYINT(1) NOT NULL DEFAULT 0,
                renewal_required TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                metadata JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_qual_def_tenant_code (tenant_id, code),
                KEY idx_qual_def_parent (parent_definition_id),
                CONSTRAINT qual_def_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /* Compat si une version antérieure avait créé une colonne mal nommée. */
    if ($hasTable('personnel_qualification_definitions') && !$hasColumn('personnel_qualification_definitions', 'level_rank')) {
        try {
            $pdo->exec('ALTER TABLE personnel_qualification_definitions ADD COLUMN level_rank INT NOT NULL DEFAULT 1 AFTER category');
        } catch (\Throwable) {
        }
        try {
            $pdo->exec('ALTER TABLE personnel_qualification_definitions DROP COLUMN `level_ Rank`');
        } catch (\Throwable) {
        }
    }

    if (!$hasTable('personnel_qualification_packs')) {
        $pdo->exec(
            "CREATE TABLE personnel_qualification_packs (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(64) NOT NULL,
                name VARCHAR(150) NOT NULL,
                description TEXT DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_qual_pack_tenant_code (tenant_id, code),
                CONSTRAINT qual_pack_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_qualification_pack_items')) {
        $pdo->exec(
            "CREATE TABLE personnel_qualification_pack_items (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                pack_id INT UNSIGNED NOT NULL,
                definition_id INT UNSIGNED NOT NULL,
                is_required TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_qual_pack_item (pack_id, definition_id),
                CONSTRAINT qual_pack_item_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT qual_pack_item_pack_fk FOREIGN KEY (pack_id) REFERENCES personnel_qualification_packs (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT qual_pack_item_def_fk FOREIGN KEY (definition_id) REFERENCES personnel_qualification_definitions (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if ($hasTable('personnel_qualifications')) {
        if (!$hasColumn('personnel_qualifications', 'definition_id')) {
            try {
                $pdo->exec('ALTER TABLE personnel_qualifications ADD COLUMN definition_id INT UNSIGNED NULL AFTER tenant_id');
            } catch (\Throwable) {
            }
        }
        if (!$hasColumn('personnel_qualifications', 'last_practiced_at')) {
            try {
                $pdo->exec('ALTER TABLE personnel_qualifications ADD COLUMN last_practiced_at DATETIME NULL AFTER expires_at');
            } catch (\Throwable) {
            }
        }
        if (!$hasColumn('personnel_qualifications', 'currency_status')) {
            try {
                $pdo->exec("ALTER TABLE personnel_qualifications ADD COLUMN currency_status ENUM('CURRENT','NON_CURRENT','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN' AFTER last_practiced_at");
            } catch (\Throwable) {
            }
        }
        if (!$hasColumn('personnel_qualifications', 'currency_expires_at')) {
            try {
                $pdo->exec('ALTER TABLE personnel_qualifications ADD COLUMN currency_expires_at DATETIME NULL AFTER currency_status');
            } catch (\Throwable) {
            }
        }
    }

    if (!$hasTable('personnel_qualification_practice_log')) {
        $pdo->exec(
            "CREATE TABLE personnel_qualification_practice_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                definition_id INT UNSIGNED DEFAULT NULL,
                qualification_id INT UNSIGNED DEFAULT NULL,
                practiced_at DATETIME NOT NULL,
                practice_type VARCHAR(64) NOT NULL DEFAULT 'ops',
                source_ref VARCHAR(120) DEFAULT NULL,
                recorded_by INT UNSIGNED DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_qual_practice_user (tenant_id, user_id, practiced_at),
                CONSTRAINT qual_practice_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT qual_practice_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_mentorships')) {
        $pdo->exec(
            "CREATE TABLE personnel_mentorships (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                mentee_user_id INT UNSIGNED NOT NULL,
                mentor_user_id INT UNSIGNED NOT NULL,
                status ENUM('ACTIVE','COMPLETED','ENDED') NOT NULL DEFAULT 'ACTIVE',
                started_at DATE NOT NULL,
                ended_at DATE DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_mentorship_mentee (tenant_id, mentee_user_id, status),
                CONSTRAINT mentorship_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_career_objectives')) {
        $pdo->exec(
            "CREATE TABLE personnel_career_objectives (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                assigned_by INT UNSIGNED DEFAULT NULL,
                title VARCHAR(200) NOT NULL,
                objective_type VARCHAR(64) NOT NULL DEFAULT 'CUSTOM',
                target_value VARCHAR(120) DEFAULT NULL,
                status ENUM('OPEN','DONE','CANCELLED') NOT NULL DEFAULT 'OPEN',
                due_date DATE DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_career_obj_user (tenant_id, user_id, status),
                CONSTRAINT career_obj_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT career_obj_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_progression_waivers')) {
        $pdo->exec(
            "CREATE TABLE personnel_progression_waivers (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                transition_id INT UNSIGNED DEFAULT NULL,
                condition_type VARCHAR(64) DEFAULT NULL,
                reason VARCHAR(255) NOT NULL,
                authority_user_id INT UNSIGNED NOT NULL,
                starts_at DATETIME NOT NULL,
                ends_at DATETIME DEFAULT NULL,
                metadata JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_waiver_user (tenant_id, user_id, ends_at),
                CONSTRAINT waiver_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT waiver_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_qualification_equivalences')) {
        $pdo->exec(
            "CREATE TABLE personnel_qualification_equivalences (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                target_definition_id INT UNSIGNED NOT NULL,
                external_label VARCHAR(200) NOT NULL,
                evidence_path VARCHAR(255) DEFAULT NULL,
                status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
                validated_by INT UNSIGNED DEFAULT NULL,
                validated_at DATETIME DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_equiv_user (tenant_id, user_id, status),
                CONSTRAINT equiv_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT equiv_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_progression_boards')) {
        $pdo->exec(
            "CREATE TABLE personnel_progression_boards (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                request_id INT UNSIGNED NOT NULL,
                quorum INT UNSIGNED NOT NULL DEFAULT 2,
                status ENUM('OPEN','DECIDED','CANCELLED') NOT NULL DEFAULT 'OPEN',
                decision ENUM('APPROVE','REJECT','DEFER') DEFAULT NULL,
                decided_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_board_request (tenant_id, request_id),
                CONSTRAINT board_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_progression_board_votes')) {
        $pdo->exec(
            "CREATE TABLE personnel_progression_board_votes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                board_id INT UNSIGNED NOT NULL,
                voter_user_id INT UNSIGNED NOT NULL,
                vote ENUM('APPROVE','REJECT','ABSTAIN') NOT NULL,
                comment TEXT DEFAULT NULL,
                voted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_board_vote (board_id, voter_user_id),
                CONSTRAINT board_vote_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT board_vote_board_fk FOREIGN KEY (board_id) REFERENCES personnel_progression_boards (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_temporary_assignments')) {
        $pdo->exec(
            "CREATE TABLE personnel_temporary_assignments (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                assignment_type ENUM('ACTING','DETACHMENT','REINFORCEMENT','INSTRUCTION','LOAN') NOT NULL DEFAULT 'ACTING',
                title VARCHAR(150) NOT NULL,
                unit_id INT UNSIGNED DEFAULT NULL,
                job_role_id INT UNSIGNED DEFAULT NULL,
                starts_at DATE NOT NULL,
                ends_at DATE DEFAULT NULL,
                does_not_change_grade TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_temp_assign_user (tenant_id, user_id, ends_at),
                CONSTRAINT temp_assign_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT temp_assign_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('orbat_billets')) {
        $pdo->exec(
            "CREATE TABLE orbat_billets (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                unit_id INT UNSIGNED NOT NULL,
                code VARCHAR(64) NOT NULL,
                title VARCHAR(150) NOT NULL,
                job_role_id INT UNSIGNED DEFAULT NULL,
                authorized_slots INT UNSIGNED NOT NULL DEFAULT 1,
                min_grade_id INT UNSIGNED DEFAULT NULL,
                required_pack_id INT UNSIGNED DEFAULT NULL,
                is_critical TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_billet_unit_code (tenant_id, unit_id, code),
                CONSTRAINT billet_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('orbat_billet_holders')) {
        $pdo->exec(
            "CREATE TABLE orbat_billet_holders (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                billet_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                holder_role ENUM('PRIMARY','ALTERNATE') NOT NULL DEFAULT 'PRIMARY',
                starts_at DATE NOT NULL,
                ends_at DATE DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_billet_holder (billet_id, holder_role, ends_at),
                CONSTRAINT billet_holder_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT billet_holder_billet_fk FOREIGN KEY (billet_id) REFERENCES orbat_billets (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT billet_holder_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_operational_capability')) {
        $pdo->exec(
            "CREATE TABLE personnel_operational_capability (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                availability ENUM('AVAILABLE','LIMITED','ABSENT','LOA','RESERVE','MEDICAL','SUSPENDED') NOT NULL DEFAULT 'AVAILABLE',
                deployable TINYINT(1) NOT NULL DEFAULT 0,
                readiness_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
                blocking_codes JSON DEFAULT NULL,
                snapshot_json JSON DEFAULT NULL,
                computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_opcap_user (tenant_id, user_id),
                CONSTRAINT opcap_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT opcap_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('unit_operational_capability')) {
        $pdo->exec(
            "CREATE TABLE unit_operational_capability (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                unit_id INT UNSIGNED NOT NULL,
                readiness_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
                deployable TINYINT(1) NOT NULL DEFAULT 0,
                manning_json JSON DEFAULT NULL,
                gaps_json JSON DEFAULT NULL,
                computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_unit_opcap (tenant_id, unit_id),
                CONSTRAINT unit_opcap_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_evidence_files')) {
        $pdo->exec(
            "CREATE TABLE personnel_evidence_files (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                related_type VARCHAR(64) NOT NULL,
                related_id INT UNSIGNED NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                original_name VARCHAR(200) DEFAULT NULL,
                visibility ENUM('MEMBER','STAFF') NOT NULL DEFAULT 'STAFF',
                uploaded_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_evidence_related (tenant_id, related_type, related_id),
                CONSTRAINT evidence_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if ($hasTable('personnel_progression_requests')) {
        if (!$hasColumn('personnel_progression_requests', 'effective_at')) {
            /* already in lot 1 */
        }
        if (!$hasColumn('personnel_progression_requests', 'is_retroactive')) {
            try {
                $pdo->exec('ALTER TABLE personnel_progression_requests ADD COLUMN is_retroactive TINYINT(1) NOT NULL DEFAULT 0 AFTER effective_at');
            } catch (\Throwable) {
            }
        }
        if (!$hasColumn('personnel_progression_requests', 'track_version')) {
            try {
                $pdo->exec('ALTER TABLE personnel_progression_requests ADD COLUMN track_version INT UNSIGNED NULL AFTER track_id');
            } catch (\Throwable) {
            }
        }
    }

    echo "  [OK] personnel_capability_axes (currency, billets, waivers, boards, capability)\n";
}
