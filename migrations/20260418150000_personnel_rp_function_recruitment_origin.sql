-- Suivi dossier RP : fonction libre sur l’unité et origine recrutement (interne / externe)
SET NAMES utf8mb4;

ALTER TABLE `personnel_profiles`
  ADD COLUMN IF NOT EXISTS `rp_operational_function` varchar(120) DEFAULT NULL AFTER `rp_recruitment_stream`,
  ADD COLUMN IF NOT EXISTS `rp_recruitment_origin` varchar(20) DEFAULT NULL AFTER `rp_operational_function`;
