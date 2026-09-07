<?php

declare(strict_types=1);

/**
 * Sessions Arma (tranches d’heures, pointage, preuves). Idempotent.
 */
function run_roleplay_game_sessions_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('roleplay_game_sessions')) {
        $pdo->exec(
            "CREATE TABLE roleplay_game_sessions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                session_uid VARCHAR(80) NOT NULL,
                server_name VARCHAR(191) NULL,
                mission_name VARCHAR(191) NULL,
                session_kind ENUM('officielle','entrainement','libre') NOT NULL DEFAULT 'libre',
                hour_category VARCHAR(80) NOT NULL DEFAULT 'Libre',
                community_event_id INT UNSIGNED NULL,
                is_official TINYINT(1) NOT NULL DEFAULT 0,
                attendance_enabled TINYINT(1) NOT NULL DEFAULT 0,
                status ENUM('discovered','open','closed','cancelled') NOT NULL DEFAULT 'discovered',
                planned_starts_at DATETIME NULL,
                planned_ends_at DATETIME NULL,
                started_at DATETIME NULL,
                ended_at DATETIME NULL,
                min_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 45,
                min_percent TINYINT UNSIGNED NOT NULL DEFAULT 50,
                late_tolerance_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
                created_by_user_id INT UNSIGNED NULL,
                notes VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_rp_gs_tenant_uid (tenant_id, session_uid),
                KEY idx_rp_gs_tenant_status (tenant_id, status, started_at),
                KEY idx_rp_gs_tenant_event (tenant_id, community_event_id),
                CONSTRAINT fk_rp_gs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('roleplay_game_session_members')) {
        $pdo->exec(
            "CREATE TABLE roleplay_game_session_members (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                session_id BIGINT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NULL,
                steam_uid VARCHAR(32) NULL,
                joined_at DATETIME NULL,
                left_at DATETIME NULL,
                last_heartbeat_at DATETIME NULL,
                raw_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                eligible_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                validated_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                attendance_status ENUM('absent','declared','detected','confirmed') NOT NULL DEFAULT 'absent',
                attendance_source VARCHAR(40) NULL,
                checked_in_at DATETIME NULL,
                checked_out_at DATETIME NULL,
                attendance_valid TINYINT(1) NOT NULL DEFAULT 0,
                rh_excluded TINYINT(1) NOT NULL DEFAULT 0,
                staff_validated_at DATETIME NULL,
                staff_validated_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_rp_gsm_session_user (session_id, user_id),
                KEY idx_rp_gsm_tenant_user (tenant_id, user_id),
                KEY idx_rp_gsm_steam (tenant_id, steam_uid),
                CONSTRAINT fk_rp_gsm_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_rp_gsm_session FOREIGN KEY (session_id) REFERENCES roleplay_game_sessions (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('roleplay_game_session_segments')) {
        $pdo->exec(
            "CREATE TABLE roleplay_game_session_segments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                session_id BIGINT UNSIGNED NOT NULL,
                member_id BIGINT UNSIGNED NOT NULL,
                reason ENUM('join','reconnect','heartbeat_timeout','mission_end','leave','check_in','check_out','staff') NOT NULL DEFAULT 'join',
                started_at DATETIME NOT NULL,
                ended_at DATETIME NULL,
                seconds INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_rp_gss_member (member_id, started_at),
                KEY idx_rp_gss_session (session_id),
                CONSTRAINT fk_rp_gss_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_rp_gss_session FOREIGN KEY (session_id) REFERENCES roleplay_game_sessions (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_rp_gss_member FOREIGN KEY (member_id) REFERENCES roleplay_game_session_members (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
