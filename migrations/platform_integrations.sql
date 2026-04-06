-- Clés d’API par communauté (intégrations) et file de tâches asynchrones.
-- Exécuté via run-migrations.php si les tables sont absentes.

CREATE TABLE IF NOT EXISTS tenant_api_keys (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    key_prefix VARCHAR(16) NOT NULL,
    key_hash VARCHAR(255) NOT NULL,
    scopes_json TEXT NULL,
    quota_per_day INT UNSIGNED NOT NULL DEFAULT 10000,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL DEFAULT NULL,
    last_used_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tenant_api_keys_prefix (key_prefix),
    KEY idx_tenant_api_keys_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_api_key_daily_usage (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    api_key_id INT UNSIGNED NOT NULL,
    usage_day DATE NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_key_day (api_key_id, usage_day),
    KEY idx_api_usage_key (api_key_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS async_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NULL,
    job_type VARCHAR(64) NOT NULL,
    payload_json MEDIUMTEXT NOT NULL,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    reserved_at DATETIME NULL DEFAULT NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_async_jobs_poll (reserved_at, available_at, attempts),
    KEY idx_async_jobs_type (job_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
