<?php

declare(strict_types=1);

/**
 * Rapports d’erreurs / bugs remontés par le pack Overwatch (Arma → Athena).
 * Idempotent.
 */
function run_atak_mod_reports_migration(PDO $pdo): void
{
    try {
        $hasTable = static function (string $table) use ($pdo): bool {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$table]);

            return (bool) $st->fetchColumn();
        };

        if ($hasTable('atak_mod_reports')) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE atak_mod_reports (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                severity VARCHAR(16) NOT NULL DEFAULT 'error',
                channel VARCHAR(64) NOT NULL DEFAULT 'Core',
                message VARCHAR(512) NOT NULL,
                detail_text TEXT NULL,
                context_json LONGTEXT NULL,
                fingerprint VARCHAR(64) DEFAULT NULL,
                source VARCHAR(32) NOT NULL DEFAULT 'auto',
                steam_uid VARCHAR(32) DEFAULT NULL,
                player_uid VARCHAR(64) DEFAULT NULL,
                player_name VARCHAR(128) DEFAULT NULL,
                callsign VARCHAR(64) DEFAULT NULL,
                client_ip VARCHAR(45) DEFAULT NULL,
                mod_version VARCHAR(32) DEFAULT NULL,
                extension_version VARCHAR(32) DEFAULT NULL,
                arma_build VARCHAR(64) DEFAULT NULL,
                hit_count INT UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_amr_severity_seen (severity, last_seen_at),
                KEY idx_amr_fingerprint (fingerprint),
                KEY idx_amr_steam (steam_uid),
                KEY idx_amr_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
        // Idempotent best-effort ; ne pas faire échouer le bootstrap API.
    }
}
