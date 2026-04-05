-- Fiche publique vitrine : consentement roster + métadonnées unité ORBAT
-- Appliqué aussi via run-migrations.php (ALTER conditionnels) pour bases existantes.

ALTER TABLE `user_profile_display_settings`
  ADD COLUMN `public_roster_opt_in` tinyint(1) NOT NULL DEFAULT 0 AFTER `fiche_show_matricule_to_others`;

ALTER TABLE `units`
  ADD COLUMN `public_blurb` text DEFAULT NULL AFTER `display_order`,
  ADD COLUMN `public_tags` json DEFAULT NULL AFTER `public_blurb`,
  ADD COLUMN `show_on_public_page` tinyint(1) NOT NULL DEFAULT 1 AFTER `public_tags`;
