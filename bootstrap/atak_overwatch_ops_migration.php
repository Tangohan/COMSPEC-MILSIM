<?php

declare(strict_types=1);

/**
 * Relais ATAK, débit de remontée jeu → serveur, option liaison via relais.
 */
return static function (PDO $pdo): void {
    $colExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS atak_ingest_traffic (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    map_id INT UNSIGNED NOT NULL DEFAULT 1,
    bucket_ts DATETIME NOT NULL,
    bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    photo_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    requests INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_atak_ingest_bucket (tenant_id, map_id, bucket_ts),
    KEY idx_atak_ingest_lookup (tenant_id, map_id, bucket_ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS atak_relays (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    map_id INT UNSIGNED NOT NULL DEFAULT 1,
    relay_uid VARCHAR(80) NOT NULL,
    pos_x DOUBLE NOT NULL DEFAULT 0,
    pos_y DOUBLE NOT NULL DEFAULT 0,
    pos_z DOUBLE NOT NULL DEFAULT 0,
    range_m DOUBLE NOT NULL DEFAULT 2000,
    alive TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_atak_relay (tenant_id, map_id, relay_uid),
    KEY idx_atak_relay_map (tenant_id, map_id, alive)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    if ($colExists($pdo, 'tenant_atak_config', 'id') || $colExists($pdo, 'tenant_atak_config', 'tenant_id')) {
        if (!$colExists($pdo, 'tenant_atak_config', 'atak_link_via_relays')) {
            $pdo->exec(
                'ALTER TABLE tenant_atak_config
                 ADD COLUMN atak_link_via_relays TINYINT(1) NOT NULL DEFAULT 0'
            );
        }
        if (!$colExists($pdo, 'tenant_atak_config', 'atak_link_via_relays_reviewed')) {
            $pdo->exec(
                'ALTER TABLE tenant_atak_config
                 ADD COLUMN atak_link_via_relays_reviewed TINYINT(1) NOT NULL DEFAULT 0'
            );
        }
    }
};
