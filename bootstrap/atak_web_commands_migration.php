<?php

declare(strict_types=1);

/**
 * Commandes du poste web vers les téléphones en jeu (drones, charges, notifications)
 * + miroir des notifications du téléphone ATAK de chaque joueur et ses préférences.
 *
 * Canal : le poste écrit dans atak_web_commands ; le mod Overwatch (connect) les lit en même
 * temps que les déclenchements de charges (GET /api/atak/explosive-timers/commands) et répond
 * par le POST des charges (clé web_cmd_ack). Les notifications remontent par le même POST
 * (clé atak_notifs).
 *
 * Idempotent — appelée par AtakWebCommandRepository::ensureSchema() (et utilisable depuis run-migrations.php).
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $create = [
        'atak_web_commands' => "CREATE TABLE atak_web_commands (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            map_id INT UNSIGNED NOT NULL DEFAULT 1,
            kind VARCHAR(16) NOT NULL,
            cmd VARCHAR(24) NOT NULL,
            target_uid VARCHAR(32) NOT NULL DEFAULT '*',
            target_ref VARCHAR(400) NOT NULL DEFAULT '',
            target_label VARCHAR(160) NOT NULL DEFAULT '',
            args_json TEXT NULL,
            wire VARCHAR(480) NOT NULL DEFAULT '',
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            result_text VARCHAR(255) NOT NULL DEFAULT '',
            result_json TEXT NULL,
            acked_by_uid VARCHAR(32) NOT NULL DEFAULT '',
            requested_by VARCHAR(120) NOT NULL DEFAULT '',
            requested_by_user_id INT UNSIGNED NULL,
            ttl_seconds INT UNSIGNED NOT NULL DEFAULT 90,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            acked_at DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_web_cmd_pending (tenant_id, map_id, status, created_at),
            KEY idx_web_cmd_target (tenant_id, target_uid, kind, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'atak_phone_notifs' => "CREATE TABLE atak_phone_notifs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            steam_uid VARCHAR(32) NOT NULL,
            ntype VARCHAR(16) NOT NULL DEFAULT 'INFO',
            message VARCHAR(400) NOT NULL DEFAULT '',
            game_time VARCHAR(8) NOT NULL DEFAULT '',
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_phone_notif_owner (tenant_id, steam_uid, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'atak_phone_notif_prefs' => "CREATE TABLE atak_phone_notif_prefs (
            tenant_id INT UNSIGNED NOT NULL,
            steam_uid VARCHAR(32) NOT NULL,
            muted_types VARCHAR(120) NOT NULL DEFAULT '',
            silent TINYINT(1) NOT NULL DEFAULT 0,
            banners TINYINT(1) NOT NULL DEFAULT 1,
            toast_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            game_unread INT UNSIGNED NOT NULL DEFAULT 0,
            last_seen_at DATETIME NULL,
            synced_at DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (tenant_id, steam_uid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($create as $table => $sql) {
        if ($tableExists($pdo, $table)) {
            continue;
        }
        try {
            $pdo->exec($sql);
            echo "  [OK] {$table}\n";
        } catch (Throwable $e) {
            echo "  [ATTENTION] {$table} : " . $e->getMessage() . "\n";
        }
    }
};
