<?php

declare(strict_types=1);

/**
 * Instantanés de liaison des terminaux (file d’attente, dernière vue).
 * Idempotent. Pas de décision métier : simple schéma.
 */
function run_atak_terminal_sync_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($hasTable('atak_terminal_sync')) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE atak_terminal_sync (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            terminal_uid VARCHAR(128) NOT NULL,
            callsign VARCHAR(64) DEFAULT NULL,
            pending INT UNSIGNED NOT NULL DEFAULT 0,
            markers INT UNSIGNED NOT NULL DEFAULT 0,
            drawings INT UNSIGNED NOT NULL DEFAULT 0,
            routes INT UNSIGNED NOT NULL DEFAULT 0,
            intel INT UNSIGNED NOT NULL DEFAULT 0,
            tiles INT UNSIGNED NOT NULL DEFAULT 0,
            last_at DATETIME DEFAULT NULL,
            reported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_atak_sync_terminal (tenant_id, terminal_uid),
            KEY idx_atak_sync_tenant_reported (tenant_id, reported_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}
