<?php

declare(strict_types=1);

/**
 * Canaux radio ATAK + colonne channel_key sur les messages.
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    if (!function_exists('schema_ensure_column')) {
        require_once __DIR__ . '/schema_ensure_column.php';
    }
    if (!function_exists('schema_table_exists')) {
        require_once __DIR__ . '/schema_ensure_column.php';
    }

    if (!schema_table_exists($pdo, 'atak_chat_channels')) {
        $pdo->exec(
            'CREATE TABLE atak_chat_channels (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                map_id INT UNSIGNED NOT NULL DEFAULT 1,
                channel_key VARCHAR(64) NOT NULL,
                label VARCHAR(120) NOT NULL,
                kind VARCHAR(16) NOT NULL DEFAULT \'custom\',
                created_by_callsign VARCHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_chat_channel (tenant_id, map_id, channel_key),
                KEY idx_atak_chat_channels_tenant_map (tenant_id, map_id),
                CONSTRAINT atak_chat_channels_tenant_fk
                    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    schema_ensure_column(
        $pdo,
        'atak_chat_messages',
        'channel_key',
        "`channel_key` varchar(64) NULL DEFAULT NULL AFTER `source`"
    );

    // Index pour filtre par canal (ignore si déjà présent).
    try {
        $st = $pdo->query(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'atak_chat_messages'
               AND INDEX_NAME = 'idx_atak_chat_channel'
             LIMIT 1"
        );
        if ($st && !$st->fetchColumn()) {
            $pdo->exec(
                'ALTER TABLE atak_chat_messages
                 ADD KEY idx_atak_chat_channel (tenant_id, map_id, channel_key, id)'
            );
        }
    } catch (Throwable) {
        // Best-effort.
    }

    // Backfill channel_key depuis le corps (échantillon large, une seule fois par ligne NULL).
    try {
        $pdo->exec(
            "UPDATE atak_chat_messages
             SET channel_key = 'groupe'
             WHERE channel_key IS NULL
               AND (UPPER(LEFT(TRIM(body), 7)) = 'GROUPE|' OR UPPER(TRIM(body)) = 'GROUPE')"
        );
        $pdo->exec(
            "UPDATE atak_chat_messages
             SET channel_key = 'commandement'
             WHERE channel_key IS NULL
               AND (UPPER(body) LIKE '%][COMMAND]%' OR UPPER(body) LIKE '%[HQ]%')"
        );
        $pdo->exec(
            "UPDATE atak_chat_messages
             SET channel_key = 'jtac'
             WHERE channel_key IS NULL AND UPPER(body) LIKE '%][JTAC]%'"
        );
        $pdo->exec(
            "UPDATE atak_chat_messages
             SET channel_key = 'air'
             WHERE channel_key IS NULL AND UPPER(body) LIKE '%][AIR]%'"
        );
        $pdo->exec(
            "UPDATE atak_chat_messages
             SET channel_key = 'general'
             WHERE channel_key IS NULL"
        );
    } catch (Throwable) {
        // Best-effort backfill.
    }
};
