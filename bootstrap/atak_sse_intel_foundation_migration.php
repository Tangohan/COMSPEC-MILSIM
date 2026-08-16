<?php

declare(strict_types=1);

/**
 * LOT 1 — Fondations Intelligence Workspace SSE.
 *
 * Ajoute un registre d’entités, une timeline d’événements normalisés,
 * un journal d’audit, et enrichit sse_cases / sse_relations sans casser le legacy.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $m): void {
        // Silence web : run-migrations.php passe un $log explicite.
    };

    $tableExists = static function (PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    };

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);

        return (bool) $stmt->fetchColumn();
    };

    // ---- Registre unifié d’entités (projection, pas un doublon métier) ----
    if (!$tableExists($pdo, 'sse_entity_index')) {
        $pdo->exec(
            "CREATE TABLE sse_entity_index (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                context_id INT UNSIGNED NOT NULL DEFAULT 1,
                entity_type VARCHAR(32) NOT NULL,
                source_table VARCHAR(64) NOT NULL,
                source_id INT UNSIGNED NOT NULL,
                display_label VARCHAR(220) NOT NULL DEFAULT '',
                reference_code VARCHAR(64) DEFAULT NULL,
                status VARCHAR(32) NOT NULL DEFAULT '',
                identity_tier VARCHAR(24) DEFAULT NULL,
                source_reliability CHAR(1) NOT NULL DEFAULT 'F',
                info_credibility TINYINT UNSIGNED NOT NULL DEFAULT 6,
                classification VARCHAR(32) NOT NULL DEFAULT 'encadrement',
                last_event_at DATETIME DEFAULT NULL,
                search_blob TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_entity_uuid (tenant_id, uuid),
                UNIQUE KEY uniq_sse_entity_source (tenant_id, source_table, source_id),
                KEY idx_sse_entity_type (tenant_id, entity_type),
                KEY idx_sse_entity_context (tenant_id, context_id),
                KEY idx_sse_entity_last (tenant_id, last_event_at),
                CONSTRAINT fk_sse_entity_index_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_entity_index\n");
    } else {
        $log("  [OK] sse_entity_index (déjà présente)\n");
    }

    // ---- Timeline d’événements normalisés ----
    if (!$tableExists($pdo, 'sse_intel_events')) {
        $pdo->exec(
            "CREATE TABLE sse_intel_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_uuid CHAR(36) NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                context_id INT UNSIGNED NOT NULL DEFAULT 1,
                case_id INT UNSIGNED DEFAULT NULL,
                interest_case_id INT UNSIGNED DEFAULT NULL,
                entity_uuid CHAR(36) DEFAULT NULL,
                event_type VARCHAR(48) NOT NULL,
                source_system VARCHAR(32) NOT NULL DEFAULT 'ARMA_SSE',
                raw_source_id VARCHAR(120) DEFAULT NULL,
                identity_tier VARCHAR(24) DEFAULT NULL,
                event_time DATETIME NOT NULL,
                author_label VARCHAR(120) DEFAULT NULL,
                unit_label VARCHAR(120) DEFAULT NULL,
                lat DOUBLE DEFAULT NULL,
                lng DOUBLE DEFAULT NULL,
                source_reliability CHAR(1) NOT NULL DEFAULT 'F',
                info_credibility TINYINT UNSIGNED NOT NULL DEFAULT 6,
                summary VARCHAR(500) NOT NULL DEFAULT '',
                payload_json MEDIUMTEXT NULL,
                idempotency_key VARCHAR(120) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_intel_event_uuid (tenant_id, event_uuid),
                UNIQUE KEY uniq_sse_intel_idem (tenant_id, idempotency_key),
                KEY idx_sse_intel_time (tenant_id, event_time),
                KEY idx_sse_intel_case (tenant_id, case_id, event_time),
                KEY idx_sse_intel_entity (tenant_id, entity_uuid, event_time),
                KEY idx_sse_intel_type (tenant_id, event_type, event_time),
                KEY idx_sse_intel_source (tenant_id, source_system),
                CONSTRAINT fk_sse_intel_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_intel_events\n");
    } else {
        $log("  [OK] sse_intel_events (déjà présente)\n");
    }

    // ---- Journal d’audit append-only ----
    if (!$tableExists($pdo, 'sse_audit_log')) {
        $pdo->exec(
            "CREATE TABLE sse_audit_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                actor_user_id INT UNSIGNED DEFAULT NULL,
                actor_label VARCHAR(160) NOT NULL DEFAULT '',
                action VARCHAR(64) NOT NULL,
                object_type VARCHAR(48) NOT NULL DEFAULT '',
                object_id INT UNSIGNED DEFAULT NULL,
                object_uuid CHAR(36) DEFAULT NULL,
                reason VARCHAR(500) DEFAULT NULL,
                before_json MEDIUMTEXT NULL,
                after_json MEDIUMTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_audit_time (tenant_id, created_at),
                KEY idx_sse_audit_object (tenant_id, object_type, object_id),
                KEY idx_sse_audit_action (tenant_id, action, created_at),
                CONSTRAINT fk_sse_audit_log_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_audit_log\n");
    } else {
        $log("  [OK] sse_audit_log (déjà présente)\n");
    }

    // ---- Enrichissement sse_cases (non destructif) ----
    if ($tableExists($pdo, 'sse_cases')) {
        $caseAlters = [
            'lifecycle_status' => "ALTER TABLE sse_cases ADD COLUMN lifecycle_status VARCHAR(32) NOT NULL DEFAULT 'BROUILLON' AFTER status",
            'priority' => "ALTER TABLE sse_cases ADD COLUMN priority VARCHAR(16) NOT NULL DEFAULT 'normale' AFTER lifecycle_status",
            'analyst_user_id' => "ALTER TABLE sse_cases ADD COLUMN analyst_user_id INT UNSIGNED DEFAULT NULL AFTER priority",
            'producing_unit' => "ALTER TABLE sse_cases ADD COLUMN producing_unit VARCHAR(120) DEFAULT NULL AFTER analyst_user_id",
            'confidence_note' => "ALTER TABLE sse_cases ADD COLUMN confidence_note VARCHAR(8) DEFAULT NULL AFTER producing_unit",
            'last_activity_at' => "ALTER TABLE sse_cases ADD COLUMN last_activity_at DATETIME DEFAULT NULL AFTER confidence_note",
            'compartment' => "ALTER TABLE sse_cases ADD COLUMN compartment VARCHAR(64) DEFAULT NULL AFTER last_activity_at",
        ];
        foreach ($caseAlters as $col => $sql) {
            if (!$columnExists($pdo, 'sse_cases', $col)) {
                $pdo->exec($sql);
                $log("  [OK] sse_cases.{$col}\n");
            }
        }
        try {
            $pdo->exec('ALTER TABLE sse_cases ADD KEY idx_sse_cases_lifecycle (tenant_id, lifecycle_status)');
        } catch (Throwable) {
        }
        try {
            $pdo->exec('ALTER TABLE sse_cases ADD KEY idx_sse_cases_activity (tenant_id, last_activity_at)');
        } catch (Throwable) {
        }

        // Mapping legacy → lifecycle (une seule fois pour les lignes encore à BROUILLON par défaut)
        $pdo->exec(
            "UPDATE sse_cases SET lifecycle_status = CASE
                WHEN status IN ('ouvert') THEN 'COLLECTE'
                WHEN status IN ('en_cours') THEN 'EN_ANALYSE'
                WHEN status IN ('a_valider', 'a_exploiter') THEN 'A_VALIDER'
                WHEN status IN ('valide', 'validé') THEN 'VALIDE'
                WHEN status IN ('diffuse', 'diffusé') THEN 'DIFFUSE'
                WHEN status IN ('clos', 'ferme', 'fermé') THEN 'CLOS'
                WHEN status IN ('archive', 'archivé') THEN 'ARCHIVE'
                ELSE lifecycle_status
            END
            WHERE lifecycle_status = 'BROUILLON'
              AND status IS NOT NULL
              AND status NOT IN ('', 'brouillon')"
        );
        $pdo->exec(
            "UPDATE sse_cases
             SET last_activity_at = COALESCE(last_activity_at, updated_at, created_at)
             WHERE last_activity_at IS NULL"
        );
        $log("  [OK] sse_cases lifecycle mapping\n");
    }

    // ---- Enrichissement sse_relations ----
    if ($tableExists($pdo, 'sse_relations')) {
        $relAlters = [
            'uuid' => "ALTER TABLE sse_relations ADD COLUMN uuid CHAR(36) DEFAULT NULL AFTER id",
            'status' => "ALTER TABLE sse_relations ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'confirmed' AFTER relation",
            'justification' => "ALTER TABLE sse_relations ADD COLUMN justification VARCHAR(500) DEFAULT NULL AFTER note",
            'source_reliability' => "ALTER TABLE sse_relations ADD COLUMN source_reliability CHAR(1) NOT NULL DEFAULT 'F' AFTER justification",
            'info_credibility' => "ALTER TABLE sse_relations ADD COLUMN info_credibility TINYINT UNSIGNED NOT NULL DEFAULT 6 AFTER source_reliability",
            'updated_at' => "ALTER TABLE sse_relations ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
            'deleted_at' => "ALTER TABLE sse_relations ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER updated_at",
        ];
        foreach ($relAlters as $col => $sql) {
            if (!$columnExists($pdo, 'sse_relations', $col)) {
                $pdo->exec($sql);
                $log("  [OK] sse_relations.{$col}\n");
            }
        }
        try {
            $pdo->exec('ALTER TABLE sse_relations ADD KEY idx_sse_relations_status (tenant_id, status, deleted_at)');
        } catch (Throwable) {
        }
        // Backfill UUID manquants
        $missing = $pdo->query(
            "SELECT id FROM sse_relations WHERE uuid IS NULL OR uuid = '' LIMIT 5000"
        )->fetchAll(PDO::FETCH_COLUMN);
        $upd = $pdo->prepare('UPDATE sse_relations SET uuid = ? WHERE id = ?');
        foreach ($missing as $rid) {
            $upd->execute([
                sprintf(
                    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    random_int(0, 0xffff),
                    random_int(0, 0xffff),
                    random_int(0, 0xffff),
                    random_int(0, 0x0fff) | 0x4000,
                    random_int(0, 0x3fff) | 0x8000,
                    random_int(0, 0xffff),
                    random_int(0, 0xffff),
                    random_int(0, 0xffff)
                ),
                (int) $rid,
            ]);
        }
        $log("  [OK] sse_relations enrichies\n");
    }

    // ---- Curseur analyste (« depuis dernière consultation ») ----
    if (!$tableExists($pdo, 'sse_analyst_cursors')) {
        $pdo->exec(
            "CREATE TABLE sse_analyst_cursors (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                last_seen_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_analyst_cursor (tenant_id, user_id),
                KEY idx_sse_analyst_cursor_seen (tenant_id, last_seen_at),
                CONSTRAINT fk_sse_analyst_cursor_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_analyst_cursors\n");
    } else {
        $log("  [OK] sse_analyst_cursors (déjà présente)\n");
    }
};
