<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class OperationalBoardAdvancedWorkflows extends AbstractMigration
{
    public function up(): void
    {
        $planningEntries = $this->table('planning_entries');

        if (!$planningEntries->hasColumn('frago_parent_entry_id')) {
            $this->execute(<<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN frago_parent_entry_id BIGINT UNSIGNED NULL AFTER linked_id
SQL);
        }
        if (!$planningEntries->hasColumn('frago_version')) {
            $this->execute(<<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN frago_version INT NOT NULL DEFAULT 1 AFTER frago_parent_entry_id
SQL);
        }
        if (!$planningEntries->hasColumn('replacement_user_id')) {
            $this->execute(<<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN replacement_user_id BIGINT UNSIGNED NULL AFTER deputy_user_id
SQL);
        }
        if (!$planningEntries->hasColumn('replacement_auto_activate')) {
            $this->execute(<<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN replacement_auto_activate TINYINT(1) NOT NULL DEFAULT 0 AFTER replacement_user_id
SQL);
        }
        if (!$planningEntries->hasColumn('phase_current')) {
            $this->execute(<<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN phase_current ENUM('phase_1','phase_2','phase_3') NOT NULL DEFAULT 'phase_1' AFTER operational_status
SQL);
        }
        if (!$planningEntries->hasColumn('phase_rules_json')) {
            $this->execute(<<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN phase_rules_json JSON NULL AFTER phase_current
SQL);
        }
        if (!$planningEntries->hasColumn('dossier_ref')) {
            $this->execute(<<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN dossier_ref VARCHAR(120) NULL AFTER map_link
SQL);
        }
        if (!$planningEntries->hasColumn('legal_constraints')) {
            $this->execute(<<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN legal_constraints TEXT NULL AFTER dossier_ref
SQL);
        }
        if (!$planningEntries->hasColumn('fire_window_start')) {
            $this->execute(<<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN fire_window_start DATETIME NULL AFTER legal_constraints
SQL);
        }
        if (!$planningEntries->hasColumn('fire_window_end')) {
            $this->execute(<<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN fire_window_end DATETIME NULL AFTER fire_window_start
SQL);
        }
        if (!$planningEntries->hasColumn('realtime_external_ref')) {
            $this->execute(<<<'SQL'
ALTER TABLE planning_entries
    ADD COLUMN realtime_external_ref VARCHAR(190) NULL AFTER fire_window_end
SQL);
        }

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_versions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `version_number` INT NOT NULL,
    `payload_json` JSON NOT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entry_version` (`planning_entry_id`, `version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_checklists` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `is_required` TINYINT(1) NOT NULL DEFAULT 1,
    `is_done` TINYINT(1) NOT NULL DEFAULT 0,
    `done_by` BIGINT UNSIGNED NULL,
    `done_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_entry_checklist_entry` (`planning_entry_id`),
    KEY `idx_entry_checklist_done` (`is_done`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_skills` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `skill_code` VARCHAR(80) NOT NULL,
    `is_mandatory` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entry_skill` (`planning_entry_id`, `skill_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `personnel_skill_validity` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `skill_code` VARCHAR(80) NOT NULL,
    `valid_until` DATE NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_skill_validity_lookup` (`tenant_id`, `user_id`, `skill_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_tags` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `tag` VARCHAR(80) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entry_tag` (`planning_entry_id`, `tag`),
    KEY `idx_entry_tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_entry_risks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `planning_entry_id` BIGINT UNSIGNED NOT NULL,
    `risk_level` ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
    `risk_label` VARCHAR(180) NOT NULL,
    `mitigation` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entry_risk_level` (`risk_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `planning_realtime_stream` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `entry_id` BIGINT UNSIGNED NULL,
    `event_type` VARCHAR(60) NOT NULL,
    `payload_json` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_realtime_stream_tenant` (`tenant_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function down(): void
    {
        throw new \RuntimeException('Migration OperationalBoardAdvancedWorkflows: retour arrière non pris en charge (données métier).');
    }
}
