<?php

declare(strict_types=1);

/**
 * Planification de mission / organisation de combat (pré-session → live → AAR).
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'mission_plans')) {
        try {
            $pdo->exec(
                "CREATE TABLE mission_plans (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    tenant_id INT UNSIGNED NOT NULL,
                    event_id INT UNSIGNED DEFAULT NULL,
                    cycle_id INT UNSIGNED DEFAULT NULL,
                    map_id INT UNSIGNED DEFAULT NULL,
                    mission_code VARCHAR(32) NOT NULL DEFAULT '',
                    title VARCHAR(191) NOT NULL,
                    operation_name VARCHAR(191) NOT NULL DEFAULT '',
                    task_force_name VARCHAR(80) NOT NULL DEFAULT '',
                    dtg VARCHAR(32) NOT NULL DEFAULT '',
                    classification VARCHAR(80) NOT NULL DEFAULT 'EXERCISE / MILSIM',
                    status ENUM('draft','published','live','closed') NOT NULL DEFAULT 'draft',
                    opord_version VARCHAR(16) NOT NULL DEFAULT '1.0',
                    phase_label VARCHAR(80) NOT NULL DEFAULT '',
                    h_hour_at DATETIME DEFAULT NULL,
                    published_at DATETIME DEFAULT NULL,
                    closed_at DATETIME DEFAULT NULL,
                    created_by_user_id INT UNSIGNED DEFAULT NULL,
                    planned_snapshot_json MEDIUMTEXT DEFAULT NULL,
                    final_snapshot_json MEDIUMTEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_mp_tenant_status (tenant_id, status),
                    KEY idx_mp_tenant_event (tenant_id, event_id),
                    KEY idx_mp_tenant_cycle (tenant_id, cycle_id),
                    CONSTRAINT fk_mp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] mission_plans\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] mission_plans : ' . $e->getMessage() . "\n";
        }
    }

    if (!$tableExists($pdo, 'mission_to_elements')) {
        try {
            $pdo->exec(
                "CREATE TABLE mission_to_elements (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    plan_id INT UNSIGNED NOT NULL,
                    parent_id INT UNSIGNED DEFAULT NULL,
                    code VARCHAR(32) NOT NULL DEFAULT '',
                    label VARCHAR(80) NOT NULL,
                    kind ENUM('hq','maneuver','air','support','attachment','other') NOT NULL DEFAULT 'maneuver',
                    authorized_strength SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    display_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_mte_plan (plan_id, display_order),
                    KEY idx_mte_parent (parent_id),
                    CONSTRAINT fk_mte_plan FOREIGN KEY (plan_id) REFERENCES mission_plans (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] mission_to_elements\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] mission_to_elements : ' . $e->getMessage() . "\n";
        }
    }

    if (!$tableExists($pdo, 'mission_to_slots')) {
        try {
            $pdo->exec(
                "CREATE TABLE mission_to_slots (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    plan_id INT UNSIGNED NOT NULL,
                    element_id INT UNSIGNED NOT NULL,
                    callsign VARCHAR(64) NOT NULL,
                    function_label VARCHAR(80) NOT NULL DEFAULT '',
                    role_code VARCHAR(32) NOT NULL DEFAULT '',
                    rank_label VARCHAR(32) NOT NULL DEFAULT '',
                    vehicle_label VARCHAR(80) NOT NULL DEFAULT '',
                    radio_primary VARCHAR(64) NOT NULL DEFAULT '',
                    radio_secondary VARCHAR(64) NOT NULL DEFAULT '',
                    equipment_notes VARCHAR(255) NOT NULL DEFAULT '',
                    display_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_mts_plan (plan_id, display_order),
                    KEY idx_mts_element (element_id),
                    CONSTRAINT fk_mts_plan FOREIGN KEY (plan_id) REFERENCES mission_plans (id) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_mts_element FOREIGN KEY (element_id) REFERENCES mission_to_elements (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] mission_to_slots\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] mission_to_slots : ' . $e->getMessage() . "\n";
        }
    }

    if (!$tableExists($pdo, 'mission_to_assignments')) {
        try {
            $pdo->exec(
                "CREATE TABLE mission_to_assignments (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    plan_id INT UNSIGNED NOT NULL,
                    slot_id INT UNSIGNED NOT NULL,
                    planned_user_id INT UNSIGNED DEFAULT NULL,
                    current_user_id INT UNSIGNED DEFAULT NULL,
                    detected_user_id INT UNSIGNED DEFAULT NULL,
                    assignment_mode ENUM('preassigned','detected','live') NOT NULL DEFAULT 'preassigned',
                    presence_status ENUM('vacant','open','confirmed','present','absent','mismatch','temporary','unreconciled') NOT NULL DEFAULT 'vacant',
                    arma_uid VARCHAR(64) NOT NULL DEFAULT '',
                    notes VARCHAR(255) NOT NULL DEFAULT '',
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_mta_slot (slot_id),
                    KEY idx_mta_plan (plan_id),
                    KEY idx_mta_current (current_user_id),
                    KEY idx_mta_planned (planned_user_id),
                    CONSTRAINT fk_mta_plan FOREIGN KEY (plan_id) REFERENCES mission_plans (id) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_mta_slot FOREIGN KEY (slot_id) REFERENCES mission_to_slots (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] mission_to_assignments\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] mission_to_assignments : ' . $e->getMessage() . "\n";
        }
    }

    if (!$tableExists($pdo, 'mission_plan_documents')) {
        try {
            $pdo->exec(
                "CREATE TABLE mission_plan_documents (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    plan_id INT UNSIGNED NOT NULL,
                    situation_enemy TEXT,
                    situation_friendly TEXT,
                    situation_attachments TEXT,
                    situation_civil TEXT,
                    mission_task VARCHAR(255) NOT NULL DEFAULT '',
                    mission_location VARCHAR(255) NOT NULL DEFAULT '',
                    mission_nlt VARCHAR(64) NOT NULL DEFAULT '',
                    mission_purpose VARCHAR(255) NOT NULL DEFAULT '',
                    execution_intent TEXT,
                    execution_concept TEXT,
                    execution_tasks TEXT,
                    execution_coordinating TEXT,
                    sustainment_logistics TEXT,
                    sustainment_medical TEXT,
                    sustainment_resupply TEXT,
                    command_command TEXT,
                    command_signal TEXT,
                    timeline_json MEDIUMTEXT DEFAULT NULL,
                    comms_json MEDIUMTEXT DEFAULT NULL,
                    vehicle_matrix_json MEDIUMTEXT DEFAULT NULL,
                    annexes_json MEDIUMTEXT DEFAULT NULL,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_mpd_plan (plan_id),
                    CONSTRAINT fk_mpd_plan FOREIGN KEY (plan_id) REFERENCES mission_plans (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] mission_plan_documents\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] mission_plan_documents : ' . $e->getMessage() . "\n";
        }
    }

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
             LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if ($tableExists($pdo, 'mission_plans')) {
        if (!$columnExists($pdo, 'mission_plans', 'phase_label')) {
            try {
                $pdo->exec("ALTER TABLE mission_plans ADD COLUMN phase_label VARCHAR(80) NOT NULL DEFAULT '' AFTER opord_version");
                echo "  [OK] mission_plans.phase_label\n";
            } catch (Throwable $e) {
                echo '  [ATTENTION] mission_plans.phase_label : ' . $e->getMessage() . "\n";
            }
        }
        if (!$columnExists($pdo, 'mission_plans', 'h_hour_at')) {
            try {
                $pdo->exec('ALTER TABLE mission_plans ADD COLUMN h_hour_at DATETIME DEFAULT NULL AFTER phase_label');
                echo "  [OK] mission_plans.h_hour_at\n";
            } catch (Throwable $e) {
                echo '  [ATTENTION] mission_plans.h_hour_at : ' . $e->getMessage() . "\n";
            }
        }
    }

    if (!$tableExists($pdo, 'mission_plan_graphics')) {
        try {
            $pdo->exec(
                "CREATE TABLE mission_plan_graphics (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    plan_id INT UNSIGNED NOT NULL,
                    code VARCHAR(48) NOT NULL,
                    label VARCHAR(80) NOT NULL DEFAULT '',
                    kind VARCHAR(16) NOT NULL DEFAULT 'obj',
                    geom_type ENUM('point','line') NOT NULL DEFAULT 'point',
                    draw_state ENUM('planned','current','completed','modified') NOT NULL DEFAULT 'planned',
                    element_code VARCHAR(32) NOT NULL DEFAULT '',
                    world_x DECIMAL(12,3) DEFAULT NULL,
                    world_y DECIMAL(12,3) DEFAULT NULL,
                    path_json MEDIUMTEXT DEFAULT NULL,
                    display_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_mpg_plan (plan_id, display_order),
                    CONSTRAINT fk_mpg_plan FOREIGN KEY (plan_id) REFERENCES mission_plans (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] mission_plan_graphics\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] mission_plan_graphics : ' . $e->getMessage() . "\n";
        }
    }

    if (!$tableExists($pdo, 'mission_plan_timeline')) {
        try {
            $pdo->exec(
                "CREATE TABLE mission_plan_timeline (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    plan_id INT UNSIGNED NOT NULL,
                    source ENUM('planned','arma','c2') NOT NULL DEFAULT 'planned',
                    event_code VARCHAR(32) NOT NULL DEFAULT '',
                    label VARCHAR(255) NOT NULL,
                    scheduled_offset_sec INT DEFAULT NULL,
                    occurred_at DATETIME DEFAULT NULL,
                    meta_json TEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_mpt_plan (plan_id, occurred_at, scheduled_offset_sec),
                    CONSTRAINT fk_mpt_plan FOREIGN KEY (plan_id) REFERENCES mission_plans (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] mission_plan_timeline\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] mission_plan_timeline : ' . $e->getMessage() . "\n";
        }
    }

    if (!$tableExists($pdo, 'mission_to_log')) {
        try {
            $pdo->exec(
                "CREATE TABLE mission_to_log (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    plan_id INT UNSIGNED NOT NULL,
                    actor_user_id INT UNSIGNED DEFAULT NULL,
                    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    message VARCHAR(255) NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_mtl_plan (plan_id, occurred_at),
                    CONSTRAINT fk_mtl_plan FOREIGN KEY (plan_id) REFERENCES mission_plans (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] mission_to_log\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] mission_to_log : ' . $e->getMessage() . "\n";
        }
    }
};
