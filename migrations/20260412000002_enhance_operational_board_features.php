<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class EnhanceOperationalBoardFeatures extends AbstractMigration
{
    public function change(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN IF NOT EXISTS validation_status ENUM('draft','validated','active','rejected') NOT NULL DEFAULT 'draft' AFTER status,
    ADD COLUMN IF NOT EXISTS validation_comment TEXT NULL AFTER validation_status,
    ADD COLUMN IF NOT EXISTS validated_by BIGINT UNSIGNED NULL AFTER validation_comment,
    ADD COLUMN IF NOT EXISTS validated_at DATETIME NULL AFTER validated_by,
    ADD COLUMN IF NOT EXISTS operational_status ENUM('planned','in_progress','suspended','completed','cancelled') NOT NULL DEFAULT 'planned' AFTER visibility_scope,
    ADD COLUMN IF NOT EXISTS security_level ENUM('unit_public','command_restricted','confidential','secret_ops') NOT NULL DEFAULT 'unit_public' AFTER operational_status,
    ADD COLUMN IF NOT EXISTS chief_user_id BIGINT UNSIGNED NULL AFTER created_by,
    ADD COLUMN IF NOT EXISTS deputy_user_id BIGINT UNSIGNED NULL AFTER chief_user_id,
    ADD COLUMN IF NOT EXISTS command_chain VARCHAR(255) NULL AFTER deputy_user_id,
    ADD COLUMN IF NOT EXISTS accountability_note VARCHAR(255) NULL AFTER command_chain,
    ADD COLUMN IF NOT EXISTS location_lat DECIMAL(10,7) NULL AFTER accountability_note,
    ADD COLUMN IF NOT EXISTS location_lng DECIMAL(10,7) NULL AFTER location_lat,
    ADD COLUMN IF NOT EXISTS operation_zone VARCHAR(255) NULL AFTER location_lng,
    ADD COLUMN IF NOT EXISTS map_link VARCHAR(255) NULL AFTER operation_zone;
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE planning_entry_assets
    ADD COLUMN IF NOT EXISTS asset_state ENUM('available','engaged','unavailable') NOT NULL DEFAULT 'available' AFTER asset_reference,
    ADD COLUMN IF NOT EXISTS asset_metadata JSON NULL AFTER asset_state;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `operational_postures` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `posture_level` ENUM('NORMAL','VIGILANCE','ALERTE','CRISE') NOT NULL DEFAULT 'NORMAL',
    `updated_by` BIGINT UNSIGNED NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_posture_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `actor_user_id` BIGINT UNSIGNED NULL,
    `action_type` ENUM('create','update','delete','status_change','assignment','note','validation','template_apply') NOT NULL,
    `summary` VARCHAR(255) NOT NULL,
    `payload_json` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entry_logs_entry` (`planning_entry_id`),
    KEY `idx_entry_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_templates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(160) NOT NULL,
    `template_type` ENUM('permanence_opj','mission_judiciaire','instruction','dispositif_securite','exercice','custom') NOT NULL DEFAULT 'custom',
    `payload_json` JSON NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_templates_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_dependencies` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entry_id` BIGINT UNSIGNED NOT NULL,
    `depends_on_entry_id` BIGINT UNSIGNED NOT NULL,
    `dependency_type` ENUM('blocked_by','requires_training','prerequisite') NOT NULL DEFAULT 'blocked_by',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entry_dependency` (`entry_id`, `depends_on_entry_id`, `dependency_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_comments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `author_user_id` BIGINT UNSIGNED NULL,
    `content` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entry_comments_entry` (`planning_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_attachments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `uploaded_by` BIGINT UNSIGNED NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(120) NULL,
    `file_size` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entry_attachments_entry` (`planning_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `target_scope` ENUM('role','unit','mission') NOT NULL,
    `target_value` VARCHAR(120) NOT NULL,
    `channel` ENUM('dashboard','email','push','atak') NOT NULL DEFAULT 'dashboard',
    `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entry_notifications_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_shares` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `source_tenant_id` BIGINT UNSIGNED NOT NULL,
    `target_tenant_id` BIGINT UNSIGNED NOT NULL,
    `share_mode` ENUM('read','contribute','command') NOT NULL DEFAULT 'read',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entry_share` (`planning_entry_id`, `target_tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_training_requirements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `training_slug` VARCHAR(160) NOT NULL,
    `is_mandatory` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entry_training_req` (`planning_entry_id`, `training_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_integrations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `source_system` ENUM('comspec','arma','atak') NOT NULL,
    `external_ref` VARCHAR(190) NOT NULL,
    `last_payload` JSON NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entry_integration` (`source_system`, `external_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }
}
