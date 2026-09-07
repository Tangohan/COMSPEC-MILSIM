<?php

declare(strict_types=1);

/**
 * Temps de jeu Arma remonté par le mod (cumul par membre / tenant).
 * Idempotent.
 */
function run_arma_playtime_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('user_arma_playtime')) {
        $pdo->exec(
            "CREATE TABLE user_arma_playtime (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                total_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
                server_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
                zeus_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
                editor_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
                last_report_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_user_arma_playtime_tenant_user (tenant_id, user_id),
                KEY idx_user_arma_playtime_tenant (tenant_id),
                CONSTRAINT fk_user_arma_playtime_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_user_arma_playtime_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        return;
    }

    if (!$hasColumn('user_arma_playtime', 'server_seconds')) {
        $pdo->exec(
            'ALTER TABLE user_arma_playtime
             ADD COLUMN server_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER total_seconds'
        );
    }
    if (!$hasColumn('user_arma_playtime', 'zeus_seconds')) {
        $pdo->exec(
            'ALTER TABLE user_arma_playtime
             ADD COLUMN zeus_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER server_seconds'
        );
    }
    if (!$hasColumn('user_arma_playtime', 'editor_seconds')) {
        $pdo->exec(
            'ALTER TABLE user_arma_playtime
             ADD COLUMN editor_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER zeus_seconds'
        );
    }
}
