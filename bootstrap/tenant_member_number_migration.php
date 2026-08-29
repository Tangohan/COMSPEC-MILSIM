<?php

declare(strict_types=1);

/**
 * Matricule d’organisation (tenant_member_number) — migration idempotente.
 * Isolation stricte par tenant_id. Ne touche pas athena_identifier ni tenant_matricule_config.
 */
function run_tenant_member_number_migration(PDO $pdo): void
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

    $hasIndex = static function (string $table, string $indexName) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $indexName]);

        return (bool) $st->fetchColumn();
    };

    if ($hasTable('users') && !$hasColumn('users', 'tenant_member_number')) {
        $after = $hasColumn('users', 'athena_identifier') ? ' AFTER `athena_identifier`' : '';
        $pdo->exec(
            "ALTER TABLE `users`
             ADD COLUMN `tenant_member_number` VARCHAR(100) NULL DEFAULT NULL
             COMMENT 'Matricule d''organisation (scopé par tenant)'{$after}"
        );
    }

    if ($hasTable('users') && $hasColumn('users', 'tenant_member_number')) {
        if (!$hasIndex('users', 'idx_users_tenant_member_number')) {
            $pdo->exec(
                'CREATE INDEX `idx_users_tenant_member_number`
                 ON `users` (`tenant_id`, `tenant_member_number`)'
            );
        }
        if (!$hasIndex('users', 'uniq_users_tenant_member_number')) {
            // Plusieurs NULL autorisés ; unicité uniquement pour les valeurs non NULL.
            try {
                $pdo->exec(
                    'ALTER TABLE `users`
                     ADD UNIQUE KEY `uniq_users_tenant_member_number` (`tenant_id`, `tenant_member_number`)'
                );
            } catch (Throwable $e) {
                // Doublons préexistants ou moteur incompatible : l’index non unique suffit.
                error_log('[tenant_member_number] unique index skipped: ' . $e->getMessage());
            }
        }
    }

    if (!$hasTable('tenant_member_number_config')) {
        $pdo->exec(
            "CREATE TABLE `tenant_member_number_config` (
                `tenant_id` INT UNSIGNED NOT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `label` VARCHAR(80) NOT NULL DEFAULT 'Matricule d''organisation',
                `mode` ENUM('free','automatic','assisted') NOT NULL DEFAULT 'free',
                `pattern` VARCHAR(120) NOT NULL DEFAULT '{PREFIX}-{NUMBER:4}',
                `prefix` VARCHAR(40) NOT NULL DEFAULT '',
                `next_sequence` INT UNSIGNED NOT NULL DEFAULT 1,
                `unique_required` TINYINT(1) NOT NULL DEFAULT 1,
                `required` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`tenant_id`),
                CONSTRAINT `tmn_config_tenant_fk`
                    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$hasTable('tenant_member_number_audit')) {
        $pdo->exec(
            "CREATE TABLE `tenant_member_number_audit` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `old_value` VARCHAR(100) DEFAULT NULL,
                `new_value` VARCHAR(100) DEFAULT NULL,
                `reason` VARCHAR(255) DEFAULT NULL,
                `actor_user_id` INT UNSIGNED DEFAULT NULL,
                `source` VARCHAR(40) NOT NULL DEFAULT 'manual',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_tmn_audit_tenant_user` (`tenant_id`, `user_id`, `created_at`),
                KEY `idx_tmn_audit_tenant_created` (`tenant_id`, `created_at`),
                CONSTRAINT `tmn_audit_tenant_fk`
                    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
