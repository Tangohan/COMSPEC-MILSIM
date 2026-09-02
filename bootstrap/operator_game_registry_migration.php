<?php

declare(strict_types=1);

/** Persistent, tenant-isolated registry of observations received from Arma. */
function run_operator_game_registry_migration(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS operator_game_profiles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NULL,
        personnel_id BIGINT UNSIGNED NULL, steam_id VARCHAR(32) NOT NULL, arma_player_uid VARCHAR(64) NULL,
        arma_player_name VARCHAR(190) NULL, callsign VARCHAR(100) NULL, display_name VARCHAR(190) NULL,
        sex_detected VARCHAR(20) NULL, blood_type_detected VARCHAR(12) NULL, face_class VARCHAR(190) NULL,
        face_texture VARCHAR(500) NULL, face_image_path VARCHAR(500) NULL, role VARCHAR(190) NULL,
        group_name VARCHAR(190) NULL, faction VARCHAR(100) NULL, side VARCHAR(30) NULL,
        loadout_json LONGTEXT NULL, equipment_json LONGTEXT NULL, medical_json LONGTEXT NULL, versions_json LONGTEXT NULL,
        overwatch_version VARCHAR(50) NULL, atak_version VARCHAR(50) NULL, arma_version VARCHAR(50) NULL,
        server_name VARCHAR(190) NULL, mission_name VARCHAR(190) NULL, mission_id VARCHAR(190) NULL, world_name VARCHAR(190) NULL,
        observation_hash CHAR(64) NOT NULL, raw_payload_json LONGTEXT NULL, sync_status VARCHAR(30) NOT NULL DEFAULT 'SYNC_OK',
        first_seen_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, last_sync_at DATETIME NOT NULL,
        UNIQUE KEY uq_operator_game_tenant_steam (tenant_id,steam_id), KEY idx_operator_game_user (tenant_id,user_id),
        CONSTRAINT fk_ogp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
        CONSTRAINT fk_ogp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS operator_game_snapshots (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id INT UNSIGNED NOT NULL, operator_game_profile_id BIGINT UNSIGNED NOT NULL,
        reason VARCHAR(40) NOT NULL, server_name VARCHAR(190) NULL, mission_name VARCHAR(190) NULL,
        identity_json LONGTEXT NULL, equipment_json LONGTEXT NULL, medical_json LONGTEXT NULL, versions_json LONGTEXT NULL,
        raw_payload_json LONGTEXT NULL, observed_at DATETIME NOT NULL,
        KEY idx_ogs_profile_date (tenant_id,operator_game_profile_id,observed_at),
        CONSTRAINT fk_ogs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
        CONSTRAINT fk_ogs_profile FOREIGN KEY (operator_game_profile_id) REFERENCES operator_game_profiles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS operator_game_discrepancies (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NULL,
        operator_game_profile_id BIGINT UNSIGNED NOT NULL, snapshot_id BIGINT UNSIGNED NULL, field_key VARCHAR(100) NOT NULL,
        category VARCHAR(40) NOT NULL, expected_value TEXT NULL, observed_value TEXT NULL, normalized_expected TEXT NULL,
        normalized_observed TEXT NULL, severity ENUM('INFO','WARNING','ERROR','CRITICAL') NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'OPEN',
        fingerprint CHAR(64) NOT NULL, detected_at DATETIME NOT NULL, last_detected_at DATETIME NOT NULL, occurrence_count INT UNSIGNED NOT NULL DEFAULT 1,
        resolved_at DATETIME NULL, resolved_by INT UNSIGNED NULL, resolution_type VARCHAR(40) NULL, comment TEXT NULL, metadata_json LONGTEXT NULL,
        UNIQUE KEY uq_ogd_active_fingerprint (fingerprint), KEY idx_ogd_health (tenant_id,status,severity),
        CONSTRAINT fk_ogd_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
        CONSTRAINT fk_ogd_profile FOREIGN KEY (operator_game_profile_id) REFERENCES operator_game_profiles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS operator_game_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id INT UNSIGNED NOT NULL, operator_game_profile_id BIGINT UNSIGNED NULL,
        steam_id VARCHAR(32) NOT NULL, event_type VARCHAR(50) NOT NULL, metadata_json LONGTEXT NULL, occurred_at DATETIME NOT NULL,
        KEY idx_oge_timeline (tenant_id,operator_game_profile_id,occurred_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS operator_game_notifications (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NULL,
        discrepancy_id BIGINT UNSIGNED NULL, type VARCHAR(50) NOT NULL, recipient VARCHAR(254) NOT NULL,
        sent_at DATETIME NULL, status VARCHAR(30) NOT NULL, message_id VARCHAR(190) NULL, error_message TEXT NULL,
        cooldown_key CHAR(64) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_ogn_cooldown (tenant_id,cooldown_key), KEY idx_ogn_user (tenant_id,user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS operator_mod_versions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id INT UNSIGNED NOT NULL, component VARCHAR(80) NOT NULL,
        minimum_version VARCHAR(50) NULL, recommended_version VARCHAR(50) NULL, latest_version VARCHAR(50) NULL,
        incompatible_versions_json LONGTEXT NULL, download_url VARCHAR(500) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_omv_tenant_component (tenant_id,component)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
