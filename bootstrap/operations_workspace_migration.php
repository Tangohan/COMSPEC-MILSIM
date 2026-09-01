<?php

declare(strict_types=1);

/**
 * Espace opérationnel Athena : opération comme entité centrale (plan, renseignement, ordres, vue terrain).
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $say = static function (string $m) use ($log): void {
        if ($log !== null) {
            $log($m);
        }
    };
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('operations')) {
        $pdo->exec(
            "CREATE TABLE operations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                uuid CHAR(36) NOT NULL,
                code VARCHAR(32) NOT NULL,
                name VARCHAR(191) NOT NULL,
                classification VARCHAR(32) NOT NULL DEFAULT 'restricted',
                status VARCHAR(32) NOT NULL DEFAULT 'draft',
                commander_user_id INT UNSIGNED DEFAULT NULL,
                start_at DATETIME DEFAULT NULL,
                end_at DATETIME DEFAULT NULL,
                current_phase_id BIGINT UNSIGNED DEFAULT NULL,
                map_id INT UNSIGNED DEFAULT NULL,
                workspace_key VARCHAR(64) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                mission_plan_id INT UNSIGNED DEFAULT NULL,
                cycle_id INT UNSIGNED DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_ops_uuid (uuid),
                UNIQUE KEY uk_ops_tenant_code (tenant_id, code),
                KEY idx_ops_tenant_status (tenant_id, status),
                CONSTRAINT fk_ops_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('operations created');
    }

    if (!$tableExists('operation_phases')) {
        $pdo->exec(
            "CREATE TABLE operation_phases (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                operation_id BIGINT UNSIGNED NOT NULL,
                seq TINYINT UNSIGNED NOT NULL DEFAULT 0,
                code VARCHAR(32) NOT NULL DEFAULT '',
                name VARCHAR(120) NOT NULL,
                intent VARCHAR(280) DEFAULT NULL,
                starts_at DATETIME DEFAULT NULL,
                ends_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_oph_op (operation_id, seq),
                CONSTRAINT fk_oph_op FOREIGN KEY (operation_id) REFERENCES operations (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_oph_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('operation_phases created');
    }

    if (!$tableExists('operation_members')) {
        $pdo->exec(
            "CREATE TABLE operation_members (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                operation_id BIGINT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                billet VARCHAR(80) DEFAULT NULL,
                element_code VARCHAR(32) DEFAULT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'assigned',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_om_op_user (operation_id, user_id),
                CONSTRAINT fk_om_op FOREIGN KEY (operation_id) REFERENCES operations (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('operation_members created');
    }

    if (!$tableExists('operation_elements')) {
        $pdo->exec(
            "CREATE TABLE operation_elements (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                operation_id BIGINT UNSIGNED NOT NULL,
                parent_id BIGINT UNSIGNED DEFAULT NULL,
                code VARCHAR(32) NOT NULL DEFAULT '',
                name VARCHAR(120) NOT NULL,
                kind VARCHAR(32) NOT NULL DEFAULT 'maneuver',
                unit_id INT UNSIGNED DEFAULT NULL,
                display_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_oe_op (operation_id, display_order),
                CONSTRAINT fk_oe_op FOREIGN KEY (operation_id) REFERENCES operations (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('operation_elements created');
    }

    if (!$tableExists('planning_overlays')) {
        $pdo->exec(
            "CREATE TABLE planning_overlays (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                operation_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                kind VARCHAR(32) NOT NULL DEFAULT 'maneuver',
                visibility VARCHAR(32) NOT NULL DEFAULT 'staff',
                workflow VARCHAR(32) NOT NULL DEFAULT 'draft',
                current_version INT UNSIGNED NOT NULL DEFAULT 1,
                published_version INT UNSIGNED DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_pov_op (operation_id, kind),
                CONSTRAINT fk_pov_op FOREIGN KEY (operation_id) REFERENCES operations (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('planning_overlays created');
    }

    if (!$tableExists('planning_overlay_versions')) {
        $pdo->exec(
            "CREATE TABLE planning_overlay_versions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                overlay_id BIGINT UNSIGNED NOT NULL,
                version INT UNSIGNED NOT NULL,
                workflow VARCHAR(32) NOT NULL DEFAULT 'draft',
                snapshot_json MEDIUMTEXT NOT NULL,
                note VARCHAR(280) DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_povv (overlay_id, version),
                CONSTRAINT fk_povv_ov FOREIGN KEY (overlay_id) REFERENCES planning_overlays (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('planning_overlay_versions created');
    }

    if (!$tableExists('planning_layers')) {
        $pdo->exec(
            "CREATE TABLE planning_layers (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                operation_id BIGINT UNSIGNED NOT NULL,
                overlay_id BIGINT UNSIGNED NOT NULL,
                parent_id BIGINT UNSIGNED DEFAULT NULL,
                name VARCHAR(120) NOT NULL,
                kind VARCHAR(32) NOT NULL DEFAULT 'maneuver',
                visible TINYINT(1) NOT NULL DEFAULT 1,
                display_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_pl_ov (overlay_id, display_order),
                CONSTRAINT fk_pl_ov FOREIGN KEY (overlay_id) REFERENCES planning_overlays (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('planning_layers created');
    }

    if (!$tableExists('planning_objects')) {
        $pdo->exec(
            "CREATE TABLE planning_objects (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                operation_id BIGINT UNSIGNED NOT NULL,
                overlay_id BIGINT UNSIGNED NOT NULL,
                layer_id BIGINT UNSIGNED DEFAULT NULL,
                uuid CHAR(36) NOT NULL,
                graphic_type VARCHAR(64) NOT NULL,
                name VARCHAR(160) NOT NULL,
                affiliation VARCHAR(32) NOT NULL DEFAULT 'friendly',
                status VARCHAR(32) NOT NULL DEFAULT 'planned',
                phase_id BIGINT UNSIGNED DEFAULT NULL,
                all_phases TINYINT(1) NOT NULL DEFAULT 1,
                element_code VARCHAR(32) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                classification VARCHAR(32) NOT NULL DEFAULT 'restricted',
                valid_from DATETIME DEFAULT NULL,
                valid_until DATETIME DEFAULT NULL,
                geometry_json MEDIUMTEXT DEFAULT NULL,
                meta_json TEXT DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                updated_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_pobj_uuid (uuid),
                KEY idx_pobj_op (operation_id, overlay_id),
                KEY idx_pobj_type (tenant_id, graphic_type),
                CONSTRAINT fk_pobj_op FOREIGN KEY (operation_id) REFERENCES operations (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('planning_objects created');
    }

    if (!$tableExists('operation_tasks')) {
        $pdo->exec(
            "CREATE TABLE operation_tasks (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                operation_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(32) NOT NULL,
                title VARCHAR(191) NOT NULL,
                assigned_element VARCHAR(32) DEFAULT NULL,
                supporting_element VARCHAR(32) DEFAULT NULL,
                h_offset VARCHAR(32) DEFAULT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'upcoming',
                overlay_object_id BIGINT UNSIGNED DEFAULT NULL,
                order_ref VARCHAR(80) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_ot_code (operation_id, code),
                CONSTRAINT fk_ot_op FOREIGN KEY (operation_id) REFERENCES operations (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('operation_tasks created');
    }

    if (!$tableExists('target_nodes')) {
        $pdo->exec(
            "CREATE TABLE target_nodes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                operation_id BIGINT UNSIGNED NOT NULL,
                target_code VARCHAR(32) NOT NULL,
                name VARCHAR(191) NOT NULL,
                classification VARCHAR(32) NOT NULL DEFAULT 'restricted',
                target_type VARCHAR(64) DEFAULT NULL,
                category VARCHAR(64) DEFAULT NULL,
                mgrs VARCHAR(32) DEFAULT NULL,
                confidence VARCHAR(32) DEFAULT NULL,
                source_reliability VARCHAR(8) DEFAULT NULL,
                last_verified_at DATETIME DEFAULT NULL,
                sse_person_id BIGINT UNSIGNED DEFAULT NULL,
                sse_case_id BIGINT UNSIGNED DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_tn_code (operation_id, target_code),
                CONSTRAINT fk_tn_op FOREIGN KEY (operation_id) REFERENCES operations (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('target_nodes created');
    }

    if (!$tableExists('operation_activity')) {
        $pdo->exec(
            "CREATE TABLE operation_activity (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                operation_id BIGINT UNSIGNED NOT NULL,
                actor_user_id INT UNSIGNED DEFAULT NULL,
                action VARCHAR(64) NOT NULL,
                object_label VARCHAR(191) DEFAULT NULL,
                details_json TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_oa_op (operation_id, id),
                CONSTRAINT fk_oa_op FOREIGN KEY (operation_id) REFERENCES operations (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('operation_activity created');
    }

    if (!$tableExists('realtime_object_locks')) {
        $pdo->exec(
            "CREATE TABLE realtime_object_locks (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                operation_id BIGINT UNSIGNED NOT NULL,
                object_uuid CHAR(36) NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_lock_obj (operation_id, object_uuid),
                CONSTRAINT fk_lock_op FOREIGN KEY (operation_id) REFERENCES operations (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('realtime_object_locks created');
    }

    if (!$tableExists('operation_orders')) {
        $pdo->exec(
            "CREATE TABLE operation_orders (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                operation_id BIGINT UNSIGNED NOT NULL,
                kind VARCHAR(32) NOT NULL DEFAULT 'opord',
                code VARCHAR(32) NOT NULL,
                title VARCHAR(191) NOT NULL,
                workflow VARCHAR(32) NOT NULL DEFAULT 'draft',
                current_version INT UNSIGNED NOT NULL DEFAULT 1,
                published_version INT UNSIGNED DEFAULT NULL,
                overlay_refs_json TEXT DEFAULT NULL,
                sections_json MEDIUMTEXT DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_oord_code (operation_id, code),
                CONSTRAINT fk_oord_op FOREIGN KEY (operation_id) REFERENCES operations (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('operation_orders created');
    }

    if ($tableExists('mission_plans') && !$columnExists('mission_plans', 'operation_id')) {
        $pdo->exec('ALTER TABLE mission_plans ADD COLUMN operation_id BIGINT UNSIGNED DEFAULT NULL');
        $say('mission_plans.operation_id added');
    }
    if ($tableExists('theatre_mission_cycles') && !$columnExists('theatre_mission_cycles', 'operation_id')) {
        $pdo->exec('ALTER TABLE theatre_mission_cycles ADD COLUMN operation_id BIGINT UNSIGNED DEFAULT NULL');
        $say('theatre_mission_cycles.operation_id added');
    }
    if ($tableExists('atak_map_shapes') && !$columnExists('atak_map_shapes', 'operation_id')) {
        try {
            $pdo->exec('ALTER TABLE atak_map_shapes ADD COLUMN operation_id BIGINT UNSIGNED DEFAULT NULL');
            $say('atak_map_shapes.operation_id added');
        } catch (Throwable) {
        }
    }
};
