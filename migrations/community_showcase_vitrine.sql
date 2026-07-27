-- Vitrine publique : places / capacité des unités + agenda public des événements.
-- Appliqué via bootstrap/community_showcase_vitrine_migration.php (idempotent).

ALTER TABLE `units`
  ADD COLUMN `public_capacity` INT UNSIGNED NULL COMMENT 'Effectif max affiché sur la vitrine' AFTER `show_on_public_page`,
  ADD COLUMN `public_open_slots` INT NULL COMMENT 'Places ouvertes (NULL = non affiché ; -1 = ouvert sans plafond)' AFTER `public_capacity`,
  ADD COLUMN `public_accent_color` VARCHAR(7) NULL COMMENT 'Couleur de bandeau section (#RRGGBB)' AFTER `public_open_slots`;

ALTER TABLE `community_events`
  ADD COLUMN `show_on_public_page` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'Visible sur l’agenda de la vitrine publique';
