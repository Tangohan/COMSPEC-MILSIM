<?php

declare(strict_types=1);

/**
 * Inscriptions d’accès anticipé (bêta) remontées par le mod Overwatch au 1er lancement.
 * Idempotent.
 */
function run_atak_beta_registrations_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($hasTable('atak_beta_registrations')) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE atak_beta_registrations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            steam_uid VARCHAR(32) DEFAULT NULL,
            player_uid VARCHAR(64) DEFAULT NULL,
            player_name VARCHAR(128) DEFAULT NULL,
            client_ip VARCHAR(45) DEFAULT NULL,
            arma_build VARCHAR(64) DEFAULT NULL,
            arma_branch VARCHAR(64) DEFAULT NULL,
            mod_version VARCHAR(32) DEFAULT NULL,
            extension_version VARCHAR(32) DEFAULT NULL,
            acknowledged_at DATETIME DEFAULT NULL,
            first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            hit_count INT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_atak_beta_steam (steam_uid),
            KEY idx_atak_beta_player_uid (player_uid),
            KEY idx_atak_beta_client_ip (client_ip),
            KEY idx_atak_beta_last_seen (last_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
