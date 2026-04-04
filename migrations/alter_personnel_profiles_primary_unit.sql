-- Ajoute primary_unit_id à personnel_profiles si la table existe mais pas la colonne.
-- À exécuter après personnel_dossier.sql si la table a été créée sans cette colonne.
-- Si la colonne existe déjà, cette migration échouera (ignorer l'erreur).

SET NAMES utf8mb4;

ALTER TABLE `personnel_profiles`
  ADD COLUMN `primary_unit_id` int unsigned DEFAULT NULL AFTER `primary_role`,
  ADD KEY `personnel_profiles_primary_unit` (`primary_unit_id`);

-- Optionnel : clé étrangère (décommenter si la table units existe)
-- ALTER TABLE `personnel_profiles`
--   ADD CONSTRAINT `personnel_profiles_primary_unit_fk` FOREIGN KEY (`primary_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
