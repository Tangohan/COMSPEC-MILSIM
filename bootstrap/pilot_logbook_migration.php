<?php

declare(strict_types=1);

/**
 * Carnet de vol : une ligne par vol. Les vols relevés par le mod (source « mod ») sont écrits à chaque envoi, par
 * mission, jour et appareil ; le pilote ajoute ses propres vols (source « pilot ») et annote les siens (fonction à bord,
 * heures de nuit, aux instruments, en patrouille, atterrissages). Ce qu'il déclare compte une fois contresigné par un
 * instructeur. Ajoute aussi aux règles du cursus pilote le seuil d'heures « dans les conditions » (nuit, IFR, patrouille).
 *
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('pilot_logbook_entries')) {
        $pdo->exec(
            "CREATE TABLE pilot_logbook_entries (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                source VARCHAR(8) NOT NULL DEFAULT 'mod',
                flown_on DATE NOT NULL,
                mission_hash CHAR(40) NOT NULL DEFAULT '',
                mission_key VARCHAR(160) DEFAULT NULL,
                vehicle_class VARCHAR(80) NOT NULL DEFAULT '',
                vehicle_label VARCHAR(120) DEFAULT NULL,
                vehicle_kind VARCHAR(8) NOT NULL DEFAULT 'heli',
                seconds INT UNSIGNED NOT NULL DEFAULT 0,
                meters INT UNSIGNED NOT NULL DEFAULT 0,
                crew_role VARCHAR(16) DEFAULT NULL,
                night_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                ifr_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                formation_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                landings SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                remarks VARCHAR(500) DEFAULT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'verified',
                reviewed_by INT UNSIGNED DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                review_note VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_logbook_mod (tenant_id, user_id, source, mission_hash, flown_on, vehicle_class),
                KEY idx_logbook_user (tenant_id, user_id, flown_on),
                KEY idx_logbook_status (tenant_id, status),
                CONSTRAINT fk_logbook_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_logbook_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] pilot_logbook_entries\n";
    }

    if ($hasTable('pilot_qualification_rules') && !$hasColumn('pilot_qualification_rules', 'min_cond_hours')) {
        $pdo->exec('ALTER TABLE pilot_qualification_rules ADD COLUMN min_cond_hours DECIMAL(7,2) NOT NULL DEFAULT 0 AFTER min_track_hours');
        // Paliers déjà installés : seuils par défaut du catalogue (1 h de nuit, 2 h aux instruments, 1 h en patrouille).
        $pdo->exec("UPDATE pilot_qualification_rules SET min_cond_hours = CASE tier WHEN 'night' THEN 1 WHEN 'ifr' THEN 2 WHEN 'form' THEN 1 ELSE 0 END");
        echo "  [OK] pilot_qualification_rules.min_cond_hours\n";
    }
};
