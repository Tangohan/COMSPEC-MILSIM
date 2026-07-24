-- Table pour les codes d'invitation de recrutement permettant une validation automatique
-- des candidatures (migration rapide de communauté)

CREATE TABLE IF NOT EXISTS `recruitment_invite_codes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(64) NOT NULL COMMENT 'Code unique utilisable par les candidats',
  `label` VARCHAR(255) DEFAULT NULL COMMENT 'Libellé interne pour identifier ce code',
  `max_uses` INT UNSIGNED DEFAULT NULL COMMENT 'Nombre maximum d\'utilisations (NULL = illimité)',
  `uses_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Nombre d\'utilisations effectuées',
  `expires_at` DATETIME DEFAULT NULL COMMENT 'Date d\'expiration du code (NULL = pas d\'expiration)',
  `auto_accept` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Valide automatiquement la candidature (1) ou la marque comme pré-approuvée (0)',
  `assign_to_opening_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID de l\'offre de recrutement à lier automatiquement',
  `default_specialty` VARCHAR(255) DEFAULT NULL COMMENT 'Spécialité par défaut à affecter',
  `metadata_json` TEXT DEFAULT NULL COMMENT 'Métadonnées additionnelles en JSON',
  `created_by` INT UNSIGNED DEFAULT NULL COMMENT 'ID de l\'utilisateur qui a créé ce code',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_tenant_code` (`tenant_id`, `code`),
  INDEX `idx_tenant_expires` (`tenant_id`, `expires_at`),
  INDEX `idx_code_lookup` (`code`),
  CONSTRAINT `fk_invite_codes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Codes d\'invitation pour validation automatique des candidatures';

-- Table de logs pour tracer l'utilisation des codes
CREATE TABLE IF NOT EXISTS `recruitment_invite_code_uses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `invite_code_id` INT UNSIGNED NOT NULL,
  `enlistment_id` INT UNSIGNED NOT NULL COMMENT 'ID de la candidature créée avec ce code',
  `code_used` VARCHAR(64) NOT NULL COMMENT 'Valeur du code au moment de l\'utilisation',
  `used_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_tenant_code` (`tenant_id`, `invite_code_id`),
  INDEX `idx_enlistment` (`enlistment_id`),
  CONSTRAINT `fk_code_uses_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_code_uses_invite` FOREIGN KEY (`invite_code_id`) REFERENCES `recruitment_invite_codes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_code_uses_enlistment` FOREIGN KEY (`enlistment_id`) REFERENCES `enlistments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique d\'utilisation des codes d\'invitation';

-- Ajouter une colonne optionnelle à la table enlistments pour tracer le code utilisé
ALTER TABLE `enlistments` 
  ADD COLUMN `invite_code_id` INT UNSIGNED DEFAULT NULL COMMENT 'Code d\'invitation utilisé pour cette candidature' AFTER `recruitment_opening_id`,
  ADD INDEX `idx_invite_code` (`invite_code_id`);
