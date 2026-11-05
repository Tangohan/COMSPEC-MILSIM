<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_event_series (
        id CHAR(32) NOT NULL PRIMARY KEY,
        tenant_id INT NOT NULL,
        created_by_user_id INT NOT NULL,
        frequency VARCHAR(16) NOT NULL,
        weekdays_json TEXT NOT NULL,
        template_json LONGTEXT NOT NULL,
        anchor_at DATETIME NOT NULL,
        cursor_at DATETIME NOT NULL,
        active TINYINT NOT NULL DEFAULT 1,
        checked_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_event_series_active (active, tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_event_discord (
        event_id INT NOT NULL PRIMARY KEY,
        tenant_id INT NOT NULL,
        destination VARCHAR(24) NOT NULL DEFAULT 'organisation',
        enabled TINYINT NOT NULL DEFAULT 1,
        rsvp_enabled TINYINT NOT NULL DEFAULT 0,
        locale VARCHAR(5) NOT NULL DEFAULT 'fr',
        channel_id VARCHAR(24) DEFAULT NULL,
        message_id VARCHAR(24) DEFAULT NULL,
        reactions_seeded TINYINT NOT NULL DEFAULT 0,
        synced_at DATETIME DEFAULT NULL,
        last_error VARCHAR(255) NOT NULL DEFAULT '',
        INDEX idx_event_discord_tenant (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_event_discord_votes (
        event_id INT NOT NULL,
        user_id INT NOT NULL,
        reaction_status VARCHAR(16) NOT NULL,
        rsvp_fingerprint CHAR(64) DEFAULT NULL,
        PRIMARY KEY (event_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
