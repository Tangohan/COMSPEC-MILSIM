<?php

declare(strict_types=1);

/**
 * Overwatch Beta — acquittements d'alertes tactiques et anneaux de géolocalisation.
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
