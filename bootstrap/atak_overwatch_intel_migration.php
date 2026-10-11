<?php

declare(strict_types=1);

/**
 * Overwatch Beta — acquittements, suppressions et accusés de lecture d'alertes tactiques,
 * anneaux de géolocalisation.
 * Idempotent — tenants neufs et historiques.
 */
return static function (\PDO $pdo, ?callable $log = null): void {
    $say = static function (string $message) use ($log): void {
        if ($log !== null) {
            $log($message);
        }
    };

    // Une ligne par alerte acquittée au poste (clé stable « chat:<id> », « beacon:<id> »…).
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS atak_overwatch_alert_acks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            map_id INT UNSIGNED NOT NULL DEFAULT 1,
            alert_key VARCHAR(96) NOT NULL,
            acked_by VARCHAR(120) NOT NULL DEFAULT '',
            acked_by_user_id INT UNSIGNED DEFAULT NULL,
            acked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_ow_alert_ack (tenant_id, map_id, alert_key),
            KEY idx_ow_alert_ack_time (tenant_id, map_id, acked_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $say('  table atak_overwatch_alert_acks prête.');

    // Alerte supprimée au poste : retirée de la liste et de la carte pour tous les postes.
    $hasCol = static function (string $table, string $col) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $st->execute([$table, $col]);

        return $st->fetchColumn() !== false;
    };
    if (!$hasCol('atak_overwatch_alert_acks', 'dismissed_at')) {
        $pdo->exec(
            "ALTER TABLE atak_overwatch_alert_acks
                ADD COLUMN dismissed_at DATETIME DEFAULT NULL AFTER acked_at,
                ADD COLUMN dismissed_by VARCHAR(120) NOT NULL DEFAULT '' AFTER dismissed_at"
        );
        $say('  colonnes dismissed_at / dismissed_by ajoutées.');
    }

    // Accusés de lecture : poste Athena qui a affiché l'alerte (un par poste et par alerte).
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS atak_overwatch_alert_seen (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            map_id INT UNSIGNED NOT NULL DEFAULT 1,
            alert_key VARCHAR(96) NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            viewer VARCHAR(120) NOT NULL DEFAULT '',
            seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_ow_alert_seen (tenant_id, map_id, alert_key, user_id),
            KEY idx_ow_alert_seen_time (tenant_id, map_id, seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $say('  table atak_overwatch_alert_seen prête.');

    // Cercle de probabilité d'une géolocalisation (téléphone GÉOLOC, demande web, saisie au poste).
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS atak_geoloc_rings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            map_id INT UNSIGNED NOT NULL DEFAULT 1,
            label VARCHAR(120) NOT NULL DEFAULT '',
            query_ref VARCHAR(64) NOT NULL DEFAULT '',
            pos_x DOUBLE NOT NULL DEFAULT 0,
            pos_y DOUBLE NOT NULL DEFAULT 0,
            radius_m INT UNSIGNED NOT NULL DEFAULT 150,
            grid_ref VARCHAR(32) NOT NULL DEFAULT '',
            source VARCHAR(16) NOT NULL DEFAULT 'web',
            author VARCHAR(120) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL,
            KEY idx_geoloc_ring_map (tenant_id, map_id, deleted_at, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $say('  table atak_geoloc_rings prête.');
};
