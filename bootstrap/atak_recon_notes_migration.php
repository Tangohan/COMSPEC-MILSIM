<?php

declare(strict_types=1);

/**
 * Notes de reconnaissance (INTEL_MARK / recon_note).
 * Idempotent — tenants neufs et historiques.
 */
return static function (\PDO $pdo, ?callable $log = null): void {
    $say = static function (string $message) use ($log): void {
        if ($log !== null) {
            $log($message);
        }
    };

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS recon_notes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            map_id INT UNSIGNED NOT NULL DEFAULT 1,
            mission_id VARCHAR(80) NOT NULL DEFAULT '',
            source_id VARCHAR(96) NOT NULL,
            event_type VARCHAR(32) NOT NULL DEFAULT 'INTEL_MARK',
            note_type VARCHAR(32) NOT NULL DEFAULT 'recon_note',
            pos_x DOUBLE NOT NULL DEFAULT 0,
            pos_y DOUBLE NOT NULL DEFAULT 0,
            pos_z DOUBLE NOT NULL DEFAULT 0,
            text VARCHAR(160) NOT NULL DEFAULT '',
            tag VARCHAR(32) NOT NULL DEFAULT '',
            author VARCHAR(80) NOT NULL DEFAULT '',
            author_uid VARCHAR(32) NOT NULL DEFAULT '',
            confidence VARCHAR(24) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_recon_source (tenant_id, source_id),
            KEY idx_recon_map_time (tenant_id, map_id, created_at),
            KEY idx_recon_author (tenant_id, author_uid, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $say('  table recon_notes prête.');
};
