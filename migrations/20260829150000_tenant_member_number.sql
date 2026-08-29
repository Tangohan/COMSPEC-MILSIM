-- Matricule d'organisation (tenant_member_number) — additive, rétrocompatible.
-- Le matricule plateforme (users.athena_identifier) et tenant_matricule_config restent inchangés.

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `tenant_member_number` VARCHAR(100) NULL DEFAULT NULL
  COMMENT 'Matricule d''organisation (scopé par tenant)' AFTER `athena_identifier`;

-- Index de recherche ; unicité tenant-scopée (MySQL autorise plusieurs NULL).
CREATE INDEX IF NOT EXISTS `idx_users_tenant_member_number`
  ON `users` (`tenant_id`, `tenant_member_number`);

-- Contrainte UNIQUE : créer uniquement si absente (voir bootstrap PHP pour l’idempotence stricte).

CREATE TABLE IF NOT EXISTS `tenant_member_number_config` (
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
  CONSTRAINT `tmn_config_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_member_number_audit` (
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
  CONSTRAINT `tmn_audit_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
