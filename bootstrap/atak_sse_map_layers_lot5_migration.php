<?php

declare(strict_types=1);

/**
 * LOT 5 — Calques ATAK SSE :
 * géolocalisation PIR / taskings, tracks / ghost tracks, historique carto.
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

    $addColumn = static function (PDO $pdo, string $table, string $column, string $ddl) use ($columnExists, $log): void {
        if ($columnExists($pdo, $table, $column)) {
            $log("  [OK] {$table}.{$column} (déjà présent)\n");

            return;
        }
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$ddl}");
        $log("  [OK] {$table}.{$column}\n");
    };

    if (!$tableExists($pdo, 'tenants')) {
        $log("  [SKIP] tenants absente — LOT 5 différé\n");

        return;
    }

    if ($tableExists($pdo, 'sse_intel_requirements')) {
        $addColumn($pdo, 'sse_intel_requirements', 'pos_x', 'pos_x DOUBLE DEFAULT NULL');
        $addColumn($pdo, 'sse_intel_requirements', 'pos_y', 'pos_y DOUBLE DEFAULT NULL');
        $addColumn(
            $pdo,
            'sse_intel_requirements',
            'visible_on_atak',
            'visible_on_atak TINYINT(1) NOT NULL DEFAULT 1'
        );
    }

    if ($tableExists($pdo, 'sse_intel_taskings')) {
        $addColumn($pdo, 'sse_intel_taskings', 'pos_x', 'pos_x DOUBLE DEFAULT NULL');
        $addColumn($pdo, 'sse_intel_taskings', 'pos_y', 'pos_y DOUBLE DEFAULT NULL');
        $addColumn(
            $pdo,
            'sse_intel_taskings',
            'visible_on_atak',
            'visible_on_atak TINYINT(1) NOT NULL DEFAULT 1'
        );
    }

    if ($tableExists($pdo, 'sse_intel_events')) {
        $addColumn($pdo, 'sse_intel_events', 'pos_x', 'pos_x DOUBLE DEFAULT NULL');
        $addColumn($pdo, 'sse_intel_events', 'pos_y', 'pos_y DOUBLE DEFAULT NULL');
    }

    if (!$tableExists($pdo, 'sse_atak_tracks')) {
        $pdo->exec(
            "CREATE TABLE sse_atak_tracks (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                uuid CHAR(36) NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                context_id INT UNSIGNED NOT NULL DEFAULT 1,
                case_id INT UNSIGNED DEFAULT NULL,
                map_id INT UNSIGNED NOT NULL DEFAULT 1,
                track_kind VARCHAR(16) NOT NULL DEFAULT 'live',
                label VARCHAR(160) NOT NULL DEFAULT '',
                callsign VARCHAR(80) DEFAULT NULL,
                color VARCHAR(16) NOT NULL DEFAULT '#67e8f9',
                points_json MEDIUMTEXT NOT NULL,
                visible_on_atak TINYINT(1) NOT NULL DEFAULT 1,
                source_unit_key VARCHAR(80) DEFAULT NULL,
                author_label VARCHAR(160) DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_atak_track_uuid (tenant_id, uuid),
                KEY idx_sse_atak_track_map (tenant_id, map_id, visible_on_atak),
                KEY idx_sse_atak_track_case (tenant_id, case_id),
                KEY idx_sse_atak_track_kind (tenant_id, track_kind),
                CONSTRAINT fk_sse_atak_track_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_atak_tracks\n");
    } else {
        $log("  [OK] sse_atak_tracks (déjà présente)\n");
    }
};
