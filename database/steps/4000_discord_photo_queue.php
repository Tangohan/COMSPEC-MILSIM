<?php

declare(strict_types=1);

/**
 * Étape « File d'envoi des photos vers Discord » (Schéma) : les photos d'un même joueur dans une même mission partent
 * en un seul message. Exécutée par run-migrations.php ; idempotente (état réel vérifié avant création).
 */

$photoQueueSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($photoQueueSchema->tableExists('discord_photo_queue')) {
    echo "  [OK] Table discord_photo_queue déjà présente.\n";
} else {
    $pdo->exec(
        "CREATE TABLE discord_photo_queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            author_key VARCHAR(190) NOT NULL DEFAULT '',
            mission_key VARCHAR(190) NOT NULL DEFAULT '',
            batch_id VARCHAR(64) NOT NULL DEFAULT '',
            dedup_hash CHAR(64) NOT NULL DEFAULT '',
            file_path VARCHAR(500) NOT NULL,
            meta_json MEDIUMTEXT NOT NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'pending',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            next_try_at DATETIME NULL,
            last_error VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            sent_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_discord_photo_queue_status (status, next_try_at),
            KEY idx_discord_photo_queue_dedup (tenant_id, dedup_hash),
            KEY idx_discord_photo_queue_group (tenant_id, author_key, mission_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "  [OK] Table discord_photo_queue créée.\n";
}
