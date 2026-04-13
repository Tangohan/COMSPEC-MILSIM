<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Communications e-mail par tenant : modèles, groupes de destinataires, campagnes.
 *
 * definition_json (groupes) — structure attendue côté code :
 * - all_members (bool) : si true, tous les membres actifs avec e-mail valide
 * - unit_ids (list<int>) : unités ORBAT concernées
 * - include_descendants (bool) : inclure les sous-unités des unit_ids
 * - role_slugs (list<string>) : rôles communauté (slugs roles.tenant_id)
 * - extra_user_ids (list<int>) : membres ajoutés manuellement
 */
final class TenantEmailCommunications extends AbstractMigration
{
    public function change(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `tenant_email_templates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `kind` ENUM('orbat','mission','activity','custom') NOT NULL,
    `name` VARCHAR(160) NOT NULL,
    `subject` VARCHAR(500) NOT NULL,
    `body_html` MEDIUMTEXT NOT NULL,
    `body_text` TEXT NULL,
    `is_prefab` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tet_tenant_kind` (`tenant_id`, `kind`),
    CONSTRAINT `tet_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `tenant_email_recipient_groups` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(160) NOT NULL,
    `description` VARCHAR(500) NULL,
    `definition_json` JSON NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_terg_tenant` (`tenant_id`),
    CONSTRAINT `terg_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `tenant_email_campaigns` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `kind` ENUM('orbat','mission','activity','custom') NOT NULL,
    `template_id` INT UNSIGNED NULL,
    `subject_snapshot` VARCHAR(500) NOT NULL,
    `sender_user_id` INT UNSIGNED NOT NULL,
    `recipient_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('queued','completed','failed_partial') NOT NULL DEFAULT 'queued',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tec_tenant_created` (`tenant_id`, `created_at`),
    KEY `idx_tec_template` (`template_id`),
    CONSTRAINT `tec_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `tec_template_fk` FOREIGN KEY (`template_id`) REFERENCES `tenant_email_templates` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        if ($this->hasTable('email_deliveries')) {
            $row = $this->fetchRow("SELECT 1 AS o FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_deliveries' AND COLUMN_NAME = 'campaign_id' LIMIT 1");
            if ($row === null || empty($row['o'])) {
                $this->execute(<<<'SQL'
ALTER TABLE `email_deliveries`
    ADD COLUMN `campaign_id` INT UNSIGNED NULL AFTER `tenant_id`,
    ADD KEY `idx_email_deliveries_campaign` (`campaign_id`),
    ADD CONSTRAINT `email_deliveries_campaign_fk` FOREIGN KEY (`campaign_id`) REFERENCES `tenant_email_campaigns` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
SQL);
            }
        }
    }
}
