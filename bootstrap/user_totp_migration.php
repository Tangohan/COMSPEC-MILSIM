<?php

declare(strict_types=1);

/**
 * Double vérification via application d’authentification (TOTP).
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $has = static function (PDO $pdo, string $column): bool {
        $chk = $pdo->query(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = " . $pdo->quote($column) . '
             LIMIT 1'
        );

        return $chk !== false && (bool) $chk->fetchColumn();
    };

    if (!$has($pdo, 'totp_enabled')) {
        $pdo->exec(
            'ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0'
        );
    }
    if (!$has($pdo, 'totp_secret')) {
        $pdo->exec(
            'ALTER TABLE users ADD COLUMN totp_secret TEXT NULL DEFAULT NULL'
        );
    }
    if (!$has($pdo, 'totp_confirmed_at')) {
        $pdo->exec(
            'ALTER TABLE users ADD COLUMN totp_confirmed_at DATETIME NULL DEFAULT NULL'
        );
    }
};
