<?php

declare(strict_types=1);

/**
 * Autorisations temporaires (24 h) d’édition avancée de fiche personnel.
 * Idempotent.
 */
function run_user_advanced_edit_grants_migration(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_advanced_edit_grants (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            granted_by INT UNSIGNED NOT NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            revoked_at DATETIME NULL DEFAULT NULL,
            revoked_by INT UNSIGNED NULL DEFAULT NULL,
            reason VARCHAR(500) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_uaeg_tenant_user_active (tenant_id, user_id, revoked_at, ends_at),
            KEY idx_uaeg_tenant_ends (tenant_id, ends_at),
            KEY idx_uaeg_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
