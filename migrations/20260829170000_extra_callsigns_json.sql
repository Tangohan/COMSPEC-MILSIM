-- Indicatifs radio supplémentaires sur le dossier opérateur (liste JSON, ≥5 emplacements côté UI).
-- Idempotent via bootstrap personnel_personal_dossier_enhancements_migration.php.

ALTER TABLE `personnel_profiles`
  ADD COLUMN IF NOT EXISTS `extra_callsigns_json` JSON NULL
  COMMENT 'Indicatifs radio supplémentaires (liste)'
  AFTER `callsign`;
