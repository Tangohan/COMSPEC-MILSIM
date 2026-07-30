<?php

declare(strict_types=1);

/**
 * Module SSE (Sensitive Site Exploitation) — fiches personnes + photos + tables futures.
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    // Silencieux hors CLI : ensureSchema() des repos ne doit pas polluer le HTML (Tacmap).
    $log = static function (string $msg): void {
        if (PHP_SAPI === 'cli') {
            echo $msg;
        }
    };

    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'tenants')) {
        $log("  [ATTENTION] tenants absente — SSE reporté\n");

        return;
    }

    if (!$tableExists($pdo, 'sse_persons')) {
        $pdo->exec(
            "CREATE TABLE sse_persons (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                context_id INT UNSIGNED NOT NULL DEFAULT 1,
                status VARCHAR(32) NOT NULL DEFAULT 'civil',
                last_name VARCHAR(120) NOT NULL DEFAULT '',
                first_name VARCHAR(120) NOT NULL DEFAULT '',
                alias VARCHAR(120) DEFAULT NULL,
                sex_apparent VARCHAR(16) DEFAULT NULL,
                age_estimated SMALLINT UNSIGNED DEFAULT NULL,
                birth_date VARCHAR(32) DEFAULT NULL,
                birth_place VARCHAR(160) DEFAULT NULL,
                nationality VARCHAR(80) DEFAULT NULL,
                language_spoken VARCHAR(80) DEFAULT NULL,
                id_document_present TINYINT(1) NOT NULL DEFAULT 0,
                id_document_type VARCHAR(64) DEFAULT NULL,
                id_document_number VARCHAR(80) DEFAULT NULL,
                distinguishing_marks TEXT NULL,
                affiliation VARCHAR(160) DEFAULT NULL,
                circumstances VARCHAR(64) DEFAULT NULL,
                statements TEXT NULL,
                confidence_level VARCHAR(32) DEFAULT NULL,
                weapons_json JSON NULL,
                equipment_json JSON NULL,
                biometrics_simulated TINYINT(1) NOT NULL DEFAULT 0,
                consent_recorded TINYINT(1) NOT NULL DEFAULT 0,
                capture_pos_x DOUBLE DEFAULT NULL,
                capture_pos_y DOUBLE DEFAULT NULL,
                capture_pos_z DOUBLE DEFAULT NULL,
                grid_reference VARCHAR(64) DEFAULT NULL,
                location_description VARCHAR(255) DEFAULT NULL,
                submitter_user_id INT UNSIGNED DEFAULT NULL,
                submitter_callsign VARCHAR(80) DEFAULT NULL,
                submitter_steam_id VARCHAR(32) DEFAULT NULL,
                target_unit_netid VARCHAR(64) DEFAULT NULL,
                primary_photo_id INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_persons_tenant_ctx (tenant_id, context_id),
                KEY idx_sse_persons_status (tenant_id, status),
                KEY idx_sse_persons_created (tenant_id, id),
                CONSTRAINT fk_sse_persons_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_persons\n");
    } else {
        $log("  [OK] sse_persons (déjà présente)\n");
    }

    if (!$tableExists($pdo, 'sse_person_photos')) {
        $pdo->exec(
            "CREATE TABLE sse_person_photos (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                person_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                image_path VARCHAR(512) NOT NULL,
                angle VARCHAR(16) NOT NULL DEFAULT 'face',
                caption VARCHAR(255) DEFAULT NULL,
                author_callsign VARCHAR(80) DEFAULT NULL,
                pos_x DOUBLE DEFAULT NULL,
                pos_y DOUBLE DEFAULT NULL,
                pos_z DOUBLE DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_photos_person (person_id),
                KEY idx_sse_photos_tenant (tenant_id),
                CONSTRAINT fk_sse_photos_person FOREIGN KEY (person_id) REFERENCES sse_persons (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_sse_photos_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_person_photos\n");
    } else {
        $log("  [OK] sse_person_photos (déjà présente)\n");
    }

    if (!$tableExists($pdo, 'sse_sites')) {
        $pdo->exec(
            "CREATE TABLE sse_sites (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                context_id INT UNSIGNED NOT NULL DEFAULT 1,
                name VARCHAR(160) NOT NULL DEFAULT '',
                site_type VARCHAR(64) NOT NULL DEFAULT 'habitation',
                status VARCHAR(32) NOT NULL DEFAULT 'ouvert',
                team_label VARCHAR(120) DEFAULT NULL,
                pos_x DOUBLE DEFAULT NULL,
                pos_y DOUBLE DEFAULT NULL,
                pos_z DOUBLE DEFAULT NULL,
                grid_reference VARCHAR(64) DEFAULT NULL,
                summary TEXT NULL,
                submitter_callsign VARCHAR(80) DEFAULT NULL,
                submitter_steam_id VARCHAR(32) DEFAULT NULL,
                closed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_sites_tenant_ctx (tenant_id, context_id),
                CONSTRAINT fk_sse_sites_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_sites\n");
    } else {
        $log("  [OK] sse_sites (déjà présente)\n");
    }

    if (!$tableExists($pdo, 'sse_site_rooms')) {
        $pdo->exec(
            "CREATE TABLE sse_site_rooms (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                site_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                label VARCHAR(120) NOT NULL DEFAULT '',
                checked TINYINT(1) NOT NULL DEFAULT 0,
                notes TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_rooms_site (site_id),
                CONSTRAINT fk_sse_rooms_site FOREIGN KEY (site_id) REFERENCES sse_sites (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_site_rooms\n");
    } else {
        $log("  [OK] sse_site_rooms (déjà présente)\n");
    }

    if (!$tableExists($pdo, 'sse_seizures')) {
        $pdo->exec(
            "CREATE TABLE sse_seizures (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                site_id INT UNSIGNED DEFAULT NULL,
                person_id INT UNSIGNED DEFAULT NULL,
                room_id INT UNSIGNED DEFAULT NULL,
                category VARCHAR(64) NOT NULL DEFAULT 'autre',
                label VARCHAR(160) NOT NULL DEFAULT '',
                quantity INT NOT NULL DEFAULT 1,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_seizures_tenant (tenant_id),
                KEY idx_sse_seizures_site (site_id),
                KEY idx_sse_seizures_person (person_id),
                CONSTRAINT fk_sse_seizures_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_seizures\n");
    } else {
        $log("  [OK] sse_seizures (déjà présente)\n");
    }

    if (!$tableExists($pdo, 'sse_custody_events')) {
        $pdo->exec(
            "CREATE TABLE sse_custody_events (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                person_id INT UNSIGNED DEFAULT NULL,
                site_id INT UNSIGNED DEFAULT NULL,
                photo_id INT UNSIGNED DEFAULT NULL,
                event_type VARCHAR(64) NOT NULL DEFAULT 'capture',
                label VARCHAR(255) NOT NULL DEFAULT '',
                actor_callsign VARCHAR(80) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_custody_tenant (tenant_id),
                KEY idx_sse_custody_person (person_id),
                CONSTRAINT fk_sse_custody_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_custody_events\n");
    } else {
        $log("  [OK] sse_custody_events (déjà présente)\n");
    }

    if (!$tableExists($pdo, 'sse_watchlist_entries')) {
        $pdo->exec(
            "CREATE TABLE sse_watchlist_entries (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                last_name VARCHAR(120) NOT NULL DEFAULT '',
                first_name VARCHAR(120) NOT NULL DEFAULT '',
                alias VARCHAR(120) DEFAULT NULL,
                threat_level VARCHAR(32) NOT NULL DEFAULT 'surveillance',
                notes TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sse_watchlist_tenant (tenant_id, active),
                CONSTRAINT fk_sse_watchlist_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_watchlist_entries\n");
    } else {
        $log("  [OK] sse_watchlist_entries (déjà présente)\n");
    }

    // ---- 1.4.12 : constat de terrain, procès-verbal ATAK, index unité ----
    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };
    $indexExists = static function (PDO $pdo, string $table, string $index): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $index]);

        return (bool) $st->fetchColumn();
    };

    if ($tableExists($pdo, 'sse_persons')) {
        $additions = [
            'medical_context_json' => 'JSON NULL',
            'signed_by_callsign' => "VARCHAR(80) DEFAULT NULL",
            'signed_terminal_uid' => "VARCHAR(64) DEFAULT NULL",
            'signed_atak_id' => "VARCHAR(64) DEFAULT NULL",
            'signed_at' => 'DATETIME DEFAULT NULL',
            'identity_query_json' => 'JSON NULL',
        ];
        foreach ($additions as $col => $ddl) {
            if ($columnExists($pdo, 'sse_persons', $col)) {
                continue;
            }
            $pdo->exec(sprintf('ALTER TABLE sse_persons ADD COLUMN %s %s', $col, $ddl));
            $log("  [OK] sse_persons.$col\n");
        }

        // Recherche de fiche par unité Arma (panneau « fiche existante »).
        if (!$indexExists($pdo, 'sse_persons', 'idx_sse_persons_unit')) {
            $pdo->exec(
                'CREATE INDEX idx_sse_persons_unit ON sse_persons (tenant_id, context_id, target_unit_netid)'
            );
            $log("  [OK] index idx_sse_persons_unit\n");
        }
    }

    // Référence lisible d'un site, saisie et citée sur le terrain.
    if ($tableExists($pdo, 'sse_sites') && !$columnExists($pdo, 'sse_sites', 'reference_code')) {
        $pdo->exec("ALTER TABLE sse_sites ADD COLUMN reference_code VARCHAR(48) NOT NULL DEFAULT '' AFTER context_id");
        $log("  [OK] sse_sites.reference_code\n");
    }
    if ($tableExists($pdo, 'sse_sites') && !$indexExists($pdo, 'sse_sites', 'idx_sse_sites_ref')) {
        $pdo->exec('CREATE INDEX idx_sse_sites_ref ON sse_sites (tenant_id, reference_code)');
        $log("  [OK] index idx_sse_sites_ref\n");
    }
    // Le dossier est l'unité de travail : un site lui est rattaché.
    if ($tableExists($pdo, 'sse_sites') && !$columnExists($pdo, 'sse_sites', 'case_id')) {
        $pdo->exec('ALTER TABLE sse_sites ADD COLUMN case_id INT UNSIGNED DEFAULT NULL AFTER context_id');
        $log("  [OK] sse_sites.case_id\n");
    }
    if ($tableExists($pdo, 'sse_sites') && !$indexExists($pdo, 'sse_sites', 'idx_sse_sites_case')) {
        $pdo->exec('CREATE INDEX idx_sse_sites_case ON sse_sites (tenant_id, case_id)');
        $log("  [OK] index idx_sse_sites_case\n");
    }
    if ($tableExists($pdo, 'sse_site_rooms') && !$indexExists($pdo, 'sse_site_rooms', 'idx_sse_rooms_tenant')) {
        $pdo->exec('CREATE INDEX idx_sse_rooms_tenant ON sse_site_rooms (tenant_id, site_id)');
        $log("  [OK] index idx_sse_rooms_tenant\n");
    }

    // Relations d'exploitation : arêtes posées à la main par l'analyste. Les arêtes
    // déduites des données (saisie trouvée sur une personne, dans une pièce…) ne sont
    // pas stockées — elles sont recalculées à la lecture, donc jamais périmées.
    if (!$tableExists($pdo, 'sse_relations') && $tableExists($pdo, 'sse_persons')) {
        $pdo->exec(
            "CREATE TABLE sse_relations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                case_id INT UNSIGNED DEFAULT NULL,
                from_type VARCHAR(16) NOT NULL DEFAULT 'person',
                from_id INT UNSIGNED NOT NULL,
                to_type VARCHAR(16) NOT NULL DEFAULT 'person',
                to_id INT UNSIGNED NOT NULL,
                relation VARCHAR(32) NOT NULL DEFAULT 'associe',
                reliability VARCHAR(16) NOT NULL DEFAULT 'unverified',
                note VARCHAR(255) DEFAULT NULL,
                author_label VARCHAR(80) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_relation (tenant_id, from_type, from_id, to_type, to_id, relation),
                KEY idx_sse_relations_case (tenant_id, case_id),
                CONSTRAINT fk_sse_relations_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_relations\n");
    }

    if (!$tableExists($pdo, 'sse_biometric_samples') && $tableExists($pdo, 'sse_persons')) {
        $pdo->exec(
            "CREATE TABLE sse_biometric_samples (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                person_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                kind VARCHAR(16) NOT NULL DEFAULT 'empreintes',
                quality TINYINT UNSIGNED DEFAULT NULL,
                lab_reference VARCHAR(48) DEFAULT NULL,
                operator_callsign VARCHAR(80) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sse_sample (person_id, kind),
                KEY idx_sse_samples_tenant (tenant_id),
                CONSTRAINT fk_sse_samples_person FOREIGN KEY (person_id) REFERENCES sse_persons (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_sse_samples_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $log("  [OK] sse_biometric_samples\n");
    } else {
        $log("  [OK] sse_biometric_samples (déjà présente ou sse_persons absente)\n");
    }
};
