-- Préférence photo site : operator (portrait) ou account (avatar compte).
-- Idempotent via bootstrap ; ce fichier documente le schéma cible.

ALTER TABLE `user_profile_display_settings`
  ADD COLUMN IF NOT EXISTS `site_photo_priority` VARCHAR(16) NOT NULL DEFAULT 'operator'
  COMMENT 'operator|account — photo prioritaire header / portail'
  AFTER `hide_personal_info`;
