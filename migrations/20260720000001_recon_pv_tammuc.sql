-- Comptes-rendus de reconnaissance : heure de captation + structure MRT / TAMMUC simplifiée
SET NAMES utf8mb4;

ALTER TABLE `recon_pv_entries`
  ADD COLUMN IF NOT EXISTS `captured_at` datetime DEFAULT NULL AFTER `grid_ref`,
  ADD COLUMN IF NOT EXISTS `terrain_text` text AFTER `captured_at`,
  ADD COLUMN IF NOT EXISTS `adversary_text` text AFTER `terrain_text`,
  ADD COLUMN IF NOT EXISTS `mission_text` text AFTER `adversary_text`,
  ADD COLUMN IF NOT EXISTS `means_text` text AFTER `mission_text`,
  ADD COLUMN IF NOT EXISTS `urgency` enum('immediate','deferred') DEFAULT NULL AFTER `means_text`,
  ADD COLUMN IF NOT EXISTS `engagement_frame_text` text AFTER `urgency`;
