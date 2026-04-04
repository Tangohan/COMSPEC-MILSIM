<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAppMaintenance extends AbstractMigration
{
    public function change(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_maintenance` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scope` VARCHAR(120) NOT NULL DEFAULT 'global',
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `title` VARCHAR(255) NOT NULL DEFAULT 'Maintenance en cours',
    `message` TEXT NULL,
    `maintenance_code` VARCHAR(80) NULL,
    `starts_at` DATETIME NULL,
    `ends_at` DATETIME NULL,
    `allow_admin_bypass` TINYINT(1) NOT NULL DEFAULT 1,
    `allowed_ips` TEXT NULL,
    `allowed_roles` TEXT NULL,
    `redirect_url` VARCHAR(255) NULL,
    `http_status` SMALLINT NOT NULL DEFAULT 503,
    `priority` INT NOT NULL DEFAULT 100,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_scope_enabled` (`scope`, `is_enabled`),
    KEY `idx_priority` (`priority`),
    KEY `idx_starts_at` (`starts_at`),
    KEY `idx_ends_at` (`ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_maintenance_audit` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `maintenance_id` BIGINT UNSIGNED NOT NULL,
    `action_type` ENUM('create','update','enable','disable','delete') NOT NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `actor_user_id` BIGINT UNSIGNED NULL,
    `actor_ip` VARCHAR(64) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_maintenance_id` (`maintenance_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }
}
