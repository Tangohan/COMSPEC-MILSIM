-- Profils de candidature réutilisables + traçabilité enrôlement (compte Athena, consentement).
-- Idempotent : exécuter une fois ou via run-migrations.php

CREATE TABLE IF NOT EXISTS `recruitment_presets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `label` varchar(120) NOT NULL,
  `payload` json NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `recruitment_presets_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Colonnes enlistments (ajoutées si absentes — voir run-migrations.php pour détection)
-- ALTER TABLE `enlistments` ADD COLUMN `submitter_user_id` int unsigned DEFAULT NULL AFTER `reviewer_comment`;
-- ALTER TABLE `enlistments` ADD COLUMN `recruitment_preset_id` int unsigned DEFAULT NULL AFTER `submitter_user_id`;
-- ALTER TABLE `enlistments` ADD COLUMN `submitted_via` varchar(20) NOT NULL DEFAULT 'guest' AFTER `recruitment_preset_id`;
-- ALTER TABLE `enlistments` ADD COLUMN `consent_sharing_at` datetime DEFAULT NULL AFTER `submitted_via`;
-- ALTER TABLE `enlistments` ADD COLUMN `shared_fields` json DEFAULT NULL AFTER `consent_sharing_at`;
-- ALTER TABLE `enlistments` ADD KEY `submitter_user_id` (`submitter_user_id`);
-- ALTER TABLE `enlistments` ADD CONSTRAINT `enlistments_submitter_user_fk` FOREIGN KEY (`submitter_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
-- ALTER TABLE `enlistments` ADD CONSTRAINT `enlistments_recruitment_preset_fk` FOREIGN KEY (`recruitment_preset_id`) REFERENCES `recruitment_presets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
