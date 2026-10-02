<?php

declare(strict_types=1);

/**
 * Fieldwatch Lot 1 — hits RF passifs (Wi‑Fi / BLE simulés) remontés jeu → poste.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS atak_rf_hits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    map_id INT UNSIGNED NOT NULL DEFAULT 1,
    emitter_uid VARCHAR(96) NOT NULL,
    label VARCHAR(255) NOT NULL DEFAULT '',
    band VARCHAR(32) NOT NULL DEFAULT 'unknown',
    signature_id VARCHAR(64) NOT NULL DEFAULT '',
    pos_x DOUBLE NOT NULL DEFAULT 0,
    pos_y DOUBLE NOT NULL DEFAULT 0,
    signal_dbm DOUBLE DEFAULT NULL,
    sensor_callsign VARCHAR(128) NOT NULL DEFAULT '',
    payload JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_atak_rf_hits_map (tenant_id, map_id, created_at),
    KEY idx_atak_rf_hits_emitter (tenant_id, map_id, emitter_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
};
