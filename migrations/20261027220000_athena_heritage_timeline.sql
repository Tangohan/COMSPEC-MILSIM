-- Patrimoine statistique ATHENA — snapshots, jalons et points de versions majeures.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS athena_heritage_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    snapshot_date DATE NOT NULL,
    operators_total INT UNSIGNED NOT NULL DEFAULT 0,
    organizations_total INT UNSIGNED NOT NULL DEFAULT 0,
    sessions_total INT UNSIGNED NOT NULL DEFAULT 0,
    distance_km DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    playtime_hours DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    version_label VARCHAR(32) NULL,
    version_adoption_pct DECIMAL(5, 2) NULL,
    metrics_json JSON NULL,
    source ENUM('live', 'backfill', 'manual') NOT NULL DEFAULT 'live',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_athena_heritage_snapshots_date (snapshot_date),
    KEY idx_athena_heritage_snapshots_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS athena_heritage_milestones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    milestone_key VARCHAR(64) NOT NULL,
    category ENUM('threshold', 'first', 'version', 'custom') NOT NULL DEFAULT 'threshold',
    occurred_at DATETIME NULL,
    date_precision ENUM('exact', 'day', 'month', 'unknown') NOT NULL DEFAULT 'exact',
    title VARCHAR(255) NOT NULL,
    narrative TEXT NOT NULL,
    value_numeric DECIMAL(16, 2) NULL,
    value_label VARCHAR(120) NULL,
    context_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_athena_heritage_milestones_key (milestone_key),
    KEY idx_athena_heritage_milestones_occurred (occurred_at),
    KEY idx_athena_heritage_milestones_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS athena_heritage_version_points (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    version VARCHAR(32) NOT NULL,
    released_at DATE NULL,
    date_precision ENUM('exact', 'day', 'month', 'unknown') NOT NULL DEFAULT 'unknown',
    title VARCHAR(255) NOT NULL,
    summary TEXT NULL,
    stats_json JSON NULL,
    changelog_ref VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_athena_heritage_version (version),
    KEY idx_athena_heritage_version_released (released_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
