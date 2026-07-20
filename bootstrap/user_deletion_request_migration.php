<?php

declare(strict_types=1);

/**
 * Colonnes de suppression de compte en self-service (RGPD, délai de rétractation).
 * Idempotent.
 */
function run_user_deletion_request_migration(PDO $pdo): void
{
    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$columnExists($pdo, 'users', 'deletion_requested_at')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN deletion_requested_at DATETIME NULL AFTER status");
    }
    if (!$columnExists($pdo, 'users', 'deletion_scheduled_at')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN deletion_scheduled_at DATETIME NULL AFTER deletion_requested_at");
    }
}
