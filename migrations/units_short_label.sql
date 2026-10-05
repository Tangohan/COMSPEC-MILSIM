-- Abrégé saisi à la main pour une unité (sinon calculé par App\Support\UnitAbbreviation).
-- Appliqué via bootstrap/units_short_label_migration.php (idempotent), aussi lancé à
-- l'enregistrement d'une fiche unité si la colonne manque encore.

ALTER TABLE `units`
  ADD COLUMN `short_label` VARCHAR(40) NULL COMMENT 'Abrégé affiché (site, ATAK) ; vide = automatique' AFTER `name`;
