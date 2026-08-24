<?php

declare(strict_types=1);

/**
 * Journal d’appareil ATAK (même traces que le fichier AppData Overwatch).
 * Idempotent.
 */
function run_atak_device_logs_migration(PDO $pdo): void
{
    try {
        $hasTable = static function (string $table) use ($pdo): bool {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$table]);

            return (bool) $st->fetchColumn();
        };

        if (!$hasTable('atak_device_logs')) {
            $pdo->exec(
                "CREATE TABLE atak_device_logs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    tenant_id INT UNSIGNED NOT NULL,
                    terminal_uid VARCHAR(64) NOT NULL,
                    callsign VARCHAR(64) DEFAULT NULL,
                    steam_uid VARCHAR(32) DEFAULT NULL,
                    player_name VARCHAR(128) DEFAULT NULL,
                    level VARCHAR(16) NOT NULL DEFAULT 'info',
                    channel VARCHAR(64) NOT NULL DEFAULT 'Core',
                    message VARCHAR(512) NOT NULL,
                    detail_text TEXT NULL,
                    raw_line VARCHAR(1024) DEFAULT NULL,
                    source VARCHAR(16) NOT NULL DEFAULT 'mod',
                    fingerprint VARCHAR(64) DEFAULT NULL,
                    logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_adl_tenant_uid_id (tenant_id, terminal_uid, id),
                    KEY idx_adl_tenant_logged (tenant_id, logged_at),
                    KEY idx_adl_tenant_fp (tenant_id, fingerprint, logged_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    } catch (Throwable) {
        // Best-effort : ne pas faire échouer le boot API.
    }
}
