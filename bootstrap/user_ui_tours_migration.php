<?php

declare(strict_types=1);

/**
 * Préférence de guide visuel (masqué / terminé) par compte.
 * Idempotent. Une ligne par couple compte × guide.
 */
function run_user_ui_tours_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($hasTable('user_ui_tours')) {
        return;
    }

    try {
        $pdo->exec(
            "CREATE TABLE user_ui_tours (
                user_id INT UNSIGNED NOT NULL,
                tour_key VARCHAR(64) NOT NULL,
                dismissed_at DATETIME NULL,
                completed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, tour_key),
                KEY idx_uut_dismissed (user_id, dismissed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        echo '  [ATTENTION] user_ui_tours : ' . $e->getMessage() . "\n";
    }
}
