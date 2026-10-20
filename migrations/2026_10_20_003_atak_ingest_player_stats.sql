-- Data Inspector : débit et taux d'erreur d'ingestion par joueur (jusqu'ici non persistés).
CREATE TABLE IF NOT EXISTS atak_ingest_player_daily (
    tenant_id INT UNSIGNED NOT NULL,
    day DATE NOT NULL,
    steam_uid VARCHAR(32) NOT NULL,
    callsign VARCHAR(100) NULL,
    bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    photo_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    requests INT UNSIGNED NOT NULL DEFAULT 0,
    events_accepted INT UNSIGNED NOT NULL DEFAULT 0,
    events_rejected INT UNSIGNED NOT NULL DEFAULT 0,
    last_at DATETIME NOT NULL,
    PRIMARY KEY (tenant_id, day, steam_uid),
    KEY idx_aipd_last (tenant_id, last_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS atak_ingest_error_daily (
    tenant_id INT UNSIGNED NOT NULL,
    day DATE NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    error_code VARCHAR(64) NOT NULL,
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    last_steam_uid VARCHAR(32) NULL,
    last_at DATETIME NOT NULL,
    PRIMARY KEY (tenant_id, day, event_type, error_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
