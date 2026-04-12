<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOperationalBoardTables extends AbstractMigration
{
    public function change(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(180) NOT NULL,
    `description` TEXT NULL,
    `entry_type` ENUM('permanence','info','mission','task','formation') NOT NULL,
    `category_id` BIGINT UNSIGNED NULL,
    `linked_type` ENUM('event','mission','formation','none') NULL,
    `linked_id` BIGINT UNSIGNED NULL,
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `all_day` TINYINT(1) NOT NULL DEFAULT 1,
    `status` ENUM('draft','active','archived','cancelled') NOT NULL DEFAULT 'draft',
    `priority` ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
    `display_order` INT NOT NULL DEFAULT 100,
    `visibility_scope` ENUM('tenant','unit','role','private') NOT NULL DEFAULT 'tenant',
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_planning_entries_tenant_type` (`tenant_id`, `entry_type`),
    KEY `idx_planning_entries_status_dates` (`status`, `start_date`, `end_date`),
    KEY `idx_planning_entries_linked` (`linked_type`, `linked_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_personnel` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_label` VARCHAR(120) NULL,
    `is_lead` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_planning_personnel` (`planning_entry_id`, `user_id`, `role_label`),
    KEY `idx_planning_personnel_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_assets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `asset_type` VARCHAR(60) NOT NULL,
    `asset_label` VARCHAR(160) NOT NULL,
    `asset_reference` VARCHAR(160) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_planning_assets_entry` (`planning_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_notes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `note_type` ENUM('consigne','info','restriction','brief') NOT NULL DEFAULT 'consigne',
    `content` TEXT NOT NULL,
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_planning_notes_entry` (`planning_entry_id`),
    KEY `idx_planning_notes_pinned` (`is_pinned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `color` VARCHAR(20) NOT NULL DEFAULT '#334155',
    `icon` VARCHAR(80) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_planning_categories_name` (`tenant_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }
}
