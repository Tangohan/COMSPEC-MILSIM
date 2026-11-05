<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS discord_companion_guilds (
        guild_id VARCHAR(24) NOT NULL PRIMARY KEY, tenant_id INT NOT NULL UNIQUE,
        guild_name VARCHAR(100) NOT NULL, channel_id VARCHAR(24) NOT NULL,
        channels_json LONGTEXT NOT NULL, synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS discord_companion_pairings (
        token_hash CHAR(64) NOT NULL PRIMARY KEY, tenant_id INT NOT NULL,
        expires_at DATETIME NOT NULL, INDEX idx_companion_pair_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
