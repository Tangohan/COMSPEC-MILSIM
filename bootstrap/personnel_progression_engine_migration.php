<?php

declare(strict_types=1);

/**
 * Moteur RH : séquences d’indicatifs + fondations progression / carrière.
 * Migration idempotente — isolation stricte par tenant_id.
 */
function run_personnel_progression_engine_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('organization_callsign_sequences')) {
        $pdo->exec(
            "CREATE TABLE organization_callsign_sequences (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                code VARCHAR(64) NOT NULL,
                mode ENUM('NUMERIC','PREFIX_NUMERIC','CUSTOM_PATTERN','MANUAL') NOT NULL DEFAULT 'PREFIX_NUMERIC',
                prefix VARCHAR(40) NOT NULL DEFAULT '',
                suffix VARCHAR(40) NOT NULL DEFAULT '',
                pattern VARCHAR(120) NOT NULL DEFAULT '{PREFIX}-{NUMBER:02}',
                start_number INT UNSIGNED NOT NULL DEFAULT 1,
                current_number INT UNSIGNED NOT NULL DEFAULT 1,
                increment_by INT UNSIGNED NOT NULL DEFAULT 1,
                padding TINYINT UNSIGNED NOT NULL DEFAULT 2,
                reuse_released TINYINT(1) NOT NULL DEFAULT 0,
                allow_manual_override TINYINT(1) NOT NULL DEFAULT 1,
                unit_change_policy ENUM('keep','regenerate','ask','none') NOT NULL DEFAULT 'keep',
                unit_id INT UNSIGNED DEFAULT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                metadata JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_callsign_seq_tenant_code (tenant_id, code),
                KEY idx_callsign_seq_tenant_active (tenant_id, is_active, is_default),
                CONSTRAINT callsign_seq_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('organization_callsign_reserved_ranges')) {
        $pdo->exec(
            "CREATE TABLE organization_callsign_reserved_ranges (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                sequence_id INT UNSIGNED NOT NULL,
                label VARCHAR(120) NOT NULL,
                range_start INT UNSIGNED NOT NULL,
                range_end INT UNSIGNED NOT NULL,
                purpose VARCHAR(80) NOT NULL DEFAULT 'command',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_callsign_range_seq (sequence_id, range_start, range_end),
                CONSTRAINT callsign_range_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT callsign_range_seq_fk FOREIGN KEY (sequence_id) REFERENCES organization_callsign_sequences (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('organization_callsign_forbidden')) {
        $pdo->exec(
            "CREATE TABLE organization_callsign_forbidden (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                sequence_id INT UNSIGNED DEFAULT NULL,
                callsign VARCHAR(80) NOT NULL,
                reason VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_callsign_forbidden (tenant_id, callsign),
                CONSTRAINT callsign_forbidden_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_callsign_history')) {
        $pdo->exec(
            "CREATE TABLE personnel_callsign_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                sequence_id INT UNSIGNED DEFAULT NULL,
                old_callsign VARCHAR(80) DEFAULT NULL,
                new_callsign VARCHAR(80) NOT NULL,
                reason VARCHAR(255) NOT NULL,
                changed_by INT UNSIGNED DEFAULT NULL,
                metadata JSON DEFAULT NULL,
                changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_callsign_hist_user (tenant_id, user_id, changed_at),
                KEY idx_callsign_hist_new (tenant_id, new_callsign),
                CONSTRAINT callsign_hist_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT callsign_hist_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_progression_tracks')) {
        $pdo->exec(
            "CREATE TABLE personnel_progression_tracks (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(64) NOT NULL,
                name VARCHAR(150) NOT NULL,
                description TEXT DEFAULT NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                status ENUM('DRAFT','REVIEW','PUBLISHED','RETIRED') NOT NULL DEFAULT 'DRAFT',
                effective_from DATE DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                metadata JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_prog_track_tenant_code_ver (tenant_id, code, version),
                KEY idx_prog_track_tenant_status (tenant_id, status, is_active),
                CONSTRAINT prog_track_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_progression_stages')) {
        $pdo->exec(
            "CREATE TABLE personnel_progression_stages (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                track_id INT UNSIGNED NOT NULL,
                code VARCHAR(64) NOT NULL,
                name VARCHAR(150) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                grade_id INT UNSIGNED DEFAULT NULL,
                role_slug VARCHAR(100) DEFAULT NULL,
                is_terminal TINYINT(1) NOT NULL DEFAULT 0,
                metadata JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_prog_stage_track_code (track_id, code),
                KEY idx_prog_stage_tenant (tenant_id, track_id, sort_order),
                CONSTRAINT prog_stage_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT prog_stage_track_fk FOREIGN KEY (track_id) REFERENCES personnel_progression_tracks (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_progression_transitions')) {
        $pdo->exec(
            "CREATE TABLE personnel_progression_transitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                track_id INT UNSIGNED NOT NULL,
                from_stage_id INT UNSIGNED NOT NULL,
                to_stage_id INT UNSIGNED NOT NULL,
                validation_mode ENUM('automatic','trainer','unit_lead','hr','command','multi') NOT NULL DEFAULT 'trainer',
                workflow_id INT UNSIGNED DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                metadata JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_prog_transition (track_id, from_stage_id, to_stage_id),
                KEY idx_prog_transition_tenant (tenant_id, track_id),
                CONSTRAINT prog_transition_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT prog_transition_track_fk FOREIGN KEY (track_id) REFERENCES personnel_progression_tracks (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT prog_transition_from_fk FOREIGN KEY (from_stage_id) REFERENCES personnel_progression_stages (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT prog_transition_to_fk FOREIGN KEY (to_stage_id) REFERENCES personnel_progression_stages (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_progression_condition_groups')) {
        $pdo->exec(
            "CREATE TABLE personnel_progression_condition_groups (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                transition_id INT UNSIGNED NOT NULL,
                group_op ENUM('ALL','ANY') NOT NULL DEFAULT 'ALL',
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_prog_cgroup_transition (transition_id, sort_order),
                CONSTRAINT prog_cgroup_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT prog_cgroup_transition_fk FOREIGN KEY (transition_id) REFERENCES personnel_progression_transitions (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_progression_conditions')) {
        $pdo->exec(
            "CREATE TABLE personnel_progression_conditions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                group_id INT UNSIGNED NOT NULL,
                condition_type VARCHAR(64) NOT NULL,
                operator VARCHAR(16) NOT NULL DEFAULT '>=',
                value_text VARCHAR(255) NOT NULL DEFAULT '',
                is_required TINYINT(1) NOT NULL DEFAULT 1,
                weight DECIMAL(6,2) DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                metadata JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_prog_cond_group (group_id, sort_order),
                CONSTRAINT prog_cond_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT prog_cond_group_fk FOREIGN KEY (group_id) REFERENCES personnel_progression_condition_groups (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_progression_memberships')) {
        $pdo->exec(
            "CREATE TABLE personnel_progression_memberships (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                track_id INT UNSIGNED NOT NULL,
                current_stage_id INT UNSIGNED DEFAULT NULL,
                status ENUM('ACTIVE','HOLD','COMPLETED','WITHDRAWN') NOT NULL DEFAULT 'ACTIVE',
                stage_entered_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_prog_membership (tenant_id, user_id, track_id),
                KEY idx_prog_membership_stage (track_id, current_stage_id),
                CONSTRAINT prog_memb_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT prog_memb_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT prog_memb_track_fk FOREIGN KEY (track_id) REFERENCES personnel_progression_tracks (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_progression_requests')) {
        $pdo->exec(
            "CREATE TABLE personnel_progression_requests (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                track_id INT UNSIGNED NOT NULL,
                transition_id INT UNSIGNED NOT NULL,
                from_stage_id INT UNSIGNED NOT NULL,
                to_stage_id INT UNSIGNED NOT NULL,
                status ENUM('NOT_ELIGIBLE','IN_PROGRESS','ELIGIBLE','WAITING_VALIDATION','APPROVED','REJECTED','PROMOTED','BLOCKED') NOT NULL DEFAULT 'IN_PROGRESS',
                eligibility_snapshot_json JSON DEFAULT NULL,
                eligible_since DATETIME DEFAULT NULL,
                decided_at DATETIME DEFAULT NULL,
                effective_at DATETIME DEFAULT NULL,
                rejection_reason VARCHAR(255) DEFAULT NULL,
                rejection_comment TEXT DEFAULT NULL,
                reevaluate_on DATE DEFAULT NULL,
                dedupe_key VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_prog_request_dedupe (tenant_id, dedupe_key),
                KEY idx_prog_request_status (tenant_id, status, updated_at),
                KEY idx_prog_request_user (tenant_id, user_id),
                CONSTRAINT prog_req_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT prog_req_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_progression_holds')) {
        $pdo->exec(
            "CREATE TABLE personnel_progression_holds (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED DEFAULT NULL,
                unit_id INT UNSIGNED DEFAULT NULL,
                track_id INT UNSIGNED DEFAULT NULL,
                reason VARCHAR(255) NOT NULL,
                starts_at DATETIME NOT NULL,
                ends_at DATETIME DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_prog_hold_tenant (tenant_id, ends_at),
                CONSTRAINT prog_hold_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('personnel_career_events')) {
        $pdo->exec(
            "CREATE TABLE personnel_career_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                event_type VARCHAR(64) NOT NULL,
                actor_user_id INT UNSIGNED DEFAULT NULL,
                metadata_json JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_career_events_user (tenant_id, user_id, created_at),
                KEY idx_career_events_type (tenant_id, event_type, created_at),
                CONSTRAINT career_events_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT career_events_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    echo "  [OK] personnel_progression_engine (callsign sequences + progression foundation)\n";
}
