-- Dates personnalisables sur les fiches publiques d’unités (tenants).
-- Appliqué via bootstrap/units_public_dates_migration.php (idempotent).

ALTER TABLE `units`
  ADD COLUMN `public_founded_on` DATE NULL COMMENT 'Date de création affichée sur la fiche publique' AFTER `public_accent_color`,
  ADD COLUMN `public_custom_date` DATE NULL COMMENT 'Date complémentaire personnalisable' AFTER `public_founded_on`,
  ADD COLUMN `public_custom_date_label` VARCHAR(80) NULL COMMENT 'Libellé de la date complémentaire' AFTER `public_custom_date`;
