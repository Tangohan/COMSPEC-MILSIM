<?php

declare(strict_types=1);

/**
 * Tracks tactiques COP (Phase D) — REALITY / OBSERVATION / ASSESSMENT.
 * Idempotent — appelée depuis run-migrations.php et AtakTacticalTracksSchema::ensure().
 */
return static function (PDO $pdo): void {
    $driver = '';
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
    }
    $isSqlite = str_contains(strtolower($driver), 'sqlite');

    if ($isSqlite) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS tactical_tracks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                track_uid TEXT NOT NULL,
                tenant_id INTEGER NOT NULL,
                map_id INTEGER NOT NULL DEFAULT 1,
                label TEXT,
                type TEXT NOT NULL DEFAULT "UNKNOWN",
                affiliation TEXT NOT NULL DEFAULT "UNKNOWN",
                layer TEXT NOT NULL DEFAULT "observation",
                status TEXT NOT NULL DEFAULT "candidate",
                confidence REAL NOT NULL DEFAULT 0.5,
                source TEXT NOT NULL DEFAULT "manual",
                pos_x REAL,
                pos_y REAL,
                pos_z REAL,
                call_sign_ref TEXT,
                meta_json TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE(tenant_id, map_id, track_uid)
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS tactical_observations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                obs_uid TEXT NOT NULL,
                tenant_id INTEGER NOT NULL,
                map_id INTEGER NOT NULL DEFAULT 1,
                track_id INTEGER,
                kind TEXT NOT NULL,
                layer TEXT NOT NULL DEFAULT "observation",
                actor TEXT,
                pos_x REAL,
                pos_y REAL,
                bearing REAL,
                confidence REAL NOT NULL DEFAULT 0.5,
                payload_json TEXT,
                created_at TEXT NOT NULL,
                UNIQUE(tenant_id, map_id, obs_uid)
            )'
        );

        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `tactical_tracks` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `track_uid` varchar(48) NOT NULL,
          `tenant_id` int unsigned NOT NULL,
          `map_id` int unsigned NOT NULL DEFAULT 1,
          `label` varchar(120) DEFAULT NULL,
          `type` varchar(32) NOT NULL DEFAULT 'UNKNOWN',
          `affiliation` varchar(32) NOT NULL DEFAULT 'UNKNOWN',
          `layer` varchar(24) NOT NULL DEFAULT 'observation',
          `status` varchar(24) NOT NULL DEFAULT 'candidate',
          `confidence` decimal(4,3) NOT NULL DEFAULT 0.500,
          `source` varchar(32) NOT NULL DEFAULT 'manual',
          `pos_x` decimal(15,4) DEFAULT NULL,
          `pos_y` decimal(15,4) DEFAULT NULL,
          `pos_z` decimal(15,4) DEFAULT NULL,
          `call_sign_ref` varchar(64) DEFAULT NULL,
          `meta_json` json DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_tactical_track` (`tenant_id`,`map_id`,`track_uid`),
          KEY `idx_tactical_tracks_map` (`tenant_id`,`map_id`,`updated_at`),
          KEY `idx_tactical_tracks_layer` (`tenant_id`,`map_id`,`layer`,`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `tactical_observations` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `obs_uid` varchar(48) NOT NULL,
          `tenant_id` int unsigned NOT NULL,
          `map_id` int unsigned NOT NULL DEFAULT 1,
          `track_id` bigint unsigned DEFAULT NULL,
          `kind` varchar(32) NOT NULL,
          `layer` varchar(24) NOT NULL DEFAULT 'observation',
          `actor` varchar(64) DEFAULT NULL,
          `pos_x` decimal(15,4) DEFAULT NULL,
          `pos_y` decimal(15,4) DEFAULT NULL,
          `bearing` decimal(10,4) DEFAULT NULL,
          `confidence` decimal(4,3) NOT NULL DEFAULT 0.500,
          `payload_json` json DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_tactical_obs` (`tenant_id`,`map_id`,`obs_uid`),
          KEY `idx_tactical_obs_track` (`track_id`,`created_at`),
          KEY `idx_tactical_obs_map` (`tenant_id`,`map_id`,`kind`,`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
};
