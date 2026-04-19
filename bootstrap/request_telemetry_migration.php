<?php

declare(strict_types=1);

function run_request_telemetry_migration(PDO $pdo): void
{
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS request_telemetry (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    request_id CHAR(36) NULL,
    method VARCHAR(8) NOT NULL,
    route_path VARCHAR(255) NOT NULL,
    status_code SMALLINT UNSIGNED NOT NULL,
    duration_ms INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rt_created (created_at),
    KEY idx_rt_route_period (route_path, created_at),
    KEY idx_rt_status_period (status_code, created_at),
    KEY idx_rt_tenant_period (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    echo "request_telemetry : OK.\n";
}
