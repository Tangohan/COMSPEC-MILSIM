<?php

declare(strict_types=1);

/**
 * LOT 3 — Terrain SSE : personne SEEK, qualité acquisition, zones site,
 * chaîne de possession matériel, photos terrain, pont digital → intel.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $m): void {
        echo $m;
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

    $addColumn = static function (
        PDO $pdo,
        callable $columnExists,
        callable $log,
        string $table,
        string $column,
        string $ddl
    ): void {
        if (!$columnExists($pdo, $table, $column)) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$ddl}");
            $log("  [OK] {$table}.{$column}\n");
        } else {
            $log("  [OK] {$table}.{$column} (déjà présent)\n");
        }
    };

    if (!$tableExists($pdo, 'sse_persons')) {
        $log("  [SKIP] sse_persons absente — LOT 3 terrain différé\n");

        return;
    }

    // ---- Personne / SEEK ----
    $addColumn($pdo, $columnExists, $log, 'sse_persons', 'subject_id',
        "subject_id VARCHAR(48) DEFAULT NULL AFTER target_unit_netid");
    $addColumn($pdo, $columnExists, $log, 'sse_persons', 'seek_stage',
        "seek_stage VARCHAR(32) NOT NULL DEFAULT 'capture' AFTER subject_id");
    $addColumn($pdo, $columnExists, $log, 'sse_persons', 'identity_tier',
        "identity_tier VARCHAR(24) NOT NULL DEFAULT 'DECLARED' AFTER seek_stage");
    $addColumn($pdo, $columnExists, $log, 'sse_persons', 'acquisition_quality_avg',
        "acquisition_quality_avg TINYINT UNSIGNED DEFAULT NULL AFTER identity_tier");

    // ---- Photos personne ----
    if ($tableExists($pdo, 'sse_person_photos')) {
        $addColumn($pdo, $columnExists, $log, 'sse_person_photos', 'photo_type',
            "photo_type VARCHAR(32) NOT NULL DEFAULT 'FACE' AFTER angle");
        $addColumn($pdo, $columnExists, $log, 'sse_person_photos', 'quality',
            "quality TINYINT UNSIGNED DEFAULT NULL AFTER photo_type");
        $addColumn($pdo, $columnExists, $log, 'sse_person_photos', 'heading',
            "heading SMALLINT DEFAULT NULL AFTER quality");
        $addColumn($pdo, $columnExists, $log, 'sse_person_photos', 'case_id',
            "case_id INT UNSIGNED DEFAULT NULL AFTER heading");
        $addColumn($pdo, $columnExists, $log, 'sse_person_photos', 'target_ref',
            "target_ref VARCHAR(80) DEFAULT NULL AFTER case_id");
        $addColumn($pdo, $columnExists, $log, 'sse_person_photos', 'metadata_json',
            "metadata_json JSON NULL AFTER target_ref");
    }

    // ---- Biométrie ----
    if ($tableExists($pdo, 'sse_biometric_samples')) {
        $addColumn($pdo, $columnExists, $log, 'sse_biometric_samples', 'laterality',
            "laterality VARCHAR(16) DEFAULT NULL AFTER kind");
        $addColumn($pdo, $columnExists, $log, 'sse_biometric_samples', 'quality_label',
            "quality_label VARCHAR(24) DEFAULT NULL AFTER quality");
        $addColumn($pdo, $columnExists, $log, 'sse_biometric_samples', 'conditions_json',
            "conditions_json JSON NULL AFTER quality_label");
    }

    // ---- Sites / zones ----
    if ($tableExists($pdo, 'sse_sites')) {
        $addColumn($pdo, $columnExists, $log, 'sse_sites', 'exploitation_pct',
            "exploitation_pct TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status");
    }
    if ($tableExists($pdo, 'sse_site_rooms')) {
        $addColumn($pdo, $columnExists, $log, 'sse_site_rooms', 'zone_type',
            "zone_type VARCHAR(32) NOT NULL DEFAULT 'ROOM' AFTER label");
        $addColumn($pdo, $columnExists, $log, 'sse_site_rooms', 'exploitation_pct',
            "exploitation_pct TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER checked");
    }

    // ---- Matériel / custody ----
    if ($tableExists($pdo, 'sse_seizures')) {
        $addColumn($pdo, $columnExists, $log, 'sse_seizures', 'custody_state',
            "custody_state VARCHAR(32) NOT NULL DEFAULT 'OBSERVED' AFTER notes");
        $addColumn($pdo, $columnExists, $log, 'sse_seizures', 'packaging',
            "packaging VARCHAR(64) DEFAULT NULL AFTER custody_state");
        $addColumn($pdo, $columnExists, $log, 'sse_seizures', 'seal_code',
            "seal_code VARCHAR(64) DEFAULT NULL AFTER packaging");
        $addColumn($pdo, $columnExists, $log, 'sse_seizures', 'sealed_at',
            "sealed_at DATETIME DEFAULT NULL AFTER seal_code");
        $addColumn($pdo, $columnExists, $log, 'sse_seizures', 'actor_callsign',
            "actor_callsign VARCHAR(80) DEFAULT NULL AFTER sealed_at");
        $addColumn($pdo, $columnExists, $log, 'sse_seizures', 'exploited_at',
            "exploited_at DATETIME DEFAULT NULL AFTER actor_callsign");
    }
    if ($tableExists($pdo, 'sse_custody_events')) {
        $addColumn($pdo, $columnExists, $log, 'sse_custody_events', 'seizure_id',
            "seizure_id INT UNSIGNED DEFAULT NULL AFTER site_id");
    }

    // ---- Photos terrain génériques (site / objet / véhicule) ----
    if (!$tableExists($pdo, 'sse_field_photos')) {
        $pdo->exec(
            "CREATE TABLE sse_field_photos (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                context_id INT UNSIGNED NOT NULL DEFAULT 1,
                case_id INT UNSIGNED DEFAULT NULL,
                person_id INT UNSIGNED DEFAULT NULL,
                site_id INT UNSIGNED DEFAULT NULL,
                seizure_id INT UNSIGNED DEFAULT NULL,
                photo_type VARCHAR(32) NOT NULL DEFAULT 'EVIDENCE',
                image_path VARCHAR(512) DEFAULT NULL,
                quality TINYINT UNSIGNED DEFAULT NULL,
                quality_label VARCHAR(24) DEFAULT NULL,
                heading SMALLINT DEFAULT NULL,
                pos_x DOUBLE DEFAULT NULL,
                pos_y DOUBLE DEFAULT NULL,
                pos_z DOUBLE DEFAULT NULL,
                grid_reference VARCHAR(64) DEFAULT NULL,
                target_ref VARCHAR(80) DEFAULT NULL,
                caption VARCHAR(255) DEFAULT NULL,
                author_callsign VARCHAR(80) DEFAULT NULL,
                metadata_json JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_field_photos_tenant (tenant_id, context_id),
                KEY idx_sse_field_photos_person (person_id),
                KEY idx_sse_field_photos_site (site_id),
                KEY idx_sse_field_photos_case (case_id),
                CONSTRAINT fk_sse_field_photos_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_field_photos\n");
    } else {
        $log("  [OK] sse_field_photos (déjà présente)\n");
    }
};
