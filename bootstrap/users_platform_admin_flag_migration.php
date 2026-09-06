<?php

declare(strict_types=1);

/**
 * Administration du site = bit sur users, plus un rôle RBAC.
 * Idempotent. Reprise des anciennes affectations « Gestionnaire de la plateforme ».
 */
if (!function_exists('run_users_platform_admin_flag_migration')) {
function run_users_platform_admin_flag_migration(PDO $pdo, ?callable $log = null): void
{
    $say = static function (string $message) use ($log): void {
        if ($log !== null) {
            $log($message);

            return;
        }
        echo $message . "\n";
    };

    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $indexExists = static function (string $table, string $index) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $index]);

        return (bool) $st->fetchColumn();
    };

    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('users')) {
        return;
    }

    if (!$columnExists('users', 'is_platform_admin')) {
        $pdo->exec(
            'ALTER TABLE users ADD COLUMN is_platform_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER status'
        );
        $say('Colonne users.is_platform_admin ajoutée.');
    }

    if (!$indexExists('users', 'idx_users_is_platform_admin')) {
        try {
            $pdo->exec('ALTER TABLE users ADD KEY idx_users_is_platform_admin (is_platform_admin)');
        } catch (Throwable) {
        }
    }

    $legacySlug = 'site_super_admin';
    $legacyId = 0;
    if ($tableExists('roles')) {
        $st = $pdo->prepare(
            'SELECT id FROM roles WHERE tenant_id IS NULL AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1'
        );
        $st->execute([$legacySlug]);
        $legacyId = (int) $st->fetchColumn();
    }

    $flagged = 0;
    if ($legacyId > 0 && $tableExists('site_role_assignments')) {
        $st = $pdo->prepare(
            'UPDATE users u
             INNER JOIN site_role_assignments sra
                ON sra.email_normalized = LOWER(TRIM(u.email)) AND sra.revoked_at IS NULL
             SET u.is_platform_admin = 1
             WHERE sra.role_id = ? AND u.is_platform_admin = 0'
        );
        $st->execute([$legacyId]);
        $flagged += (int) $st->rowCount();
    }

    if ($legacyId > 0 && $tableExists('user_roles')) {
        $st = $pdo->prepare(
            'UPDATE users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             SET u.is_platform_admin = 1
             WHERE ur.role_id = ? AND u.is_platform_admin = 0'
        );
        $st->execute([$legacyId]);
        $flagged += (int) $st->rowCount();
    }

    if ($legacyId > 0) {
        $st = $pdo->prepare(
            'UPDATE users SET is_platform_admin = 1 WHERE role_id = ? AND is_platform_admin = 0'
        );
        $st->execute([$legacyId]);
        $flagged += (int) $st->rowCount();
    }

    $pdo->exec(
        'UPDATE users u
         INNER JOIN users src ON LOWER(TRIM(src.email)) = LOWER(TRIM(u.email)) AND src.is_platform_admin = 1
         SET u.is_platform_admin = 1
         WHERE u.is_platform_admin = 0'
    );

    if ($legacyId > 0 && $tableExists('site_role_assignments')) {
        $st = $pdo->prepare(
            'UPDATE site_role_assignments SET revoked_at = UTC_TIMESTAMP()
             WHERE role_id = ? AND revoked_at IS NULL'
        );
        $st->execute([$legacyId]);
    }

    if ($flagged > 0) {
        $say('Administration du site reprise depuis l’ancien rôle pour ' . $flagged . ' fiche(s).');
    }
}
}

return 'run_users_platform_admin_flag_migration';
