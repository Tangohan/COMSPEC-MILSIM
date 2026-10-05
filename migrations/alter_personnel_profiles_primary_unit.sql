-- Ajoute primary_unit_id à personnel_profiles si la table existe mais pas la colonne.
-- À exécuter après personnel_dossier.sql si la table a été créée sans cette colonne.
-- Si la colonne existe déjà, le pipeline note « déjà en place » (erreur 1060) et continue.
-- Pas d'AFTER `primary_role` : cette colonne n'existe pas dans le schéma actuel.

SET NAMES utf8mb4;

ALTER TABLE `personnel_profiles`
  ADD COLUMN `primary_unit_id` int unsigned DEFAULT NULL,
  ADD KEY `personnel_profiles_primary_unit` (`primary_unit_id`);

-- Optionnel : clé étrangère (décommenter si la table units existe)
-- ALTER TABLE `personnel_profiles`
--   ADD CONSTRAINT `personnel_profiles_primary_unit_fk` FOREIGN KEY (`primary_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
