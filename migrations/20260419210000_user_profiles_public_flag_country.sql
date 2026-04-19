-- Drapeau vitrine fiche personnelle (code pays ISO affiché publiquement, optionnel)
SET NAMES utf8mb4;

ALTER TABLE `user_profiles`
  ADD COLUMN `public_flag_country_code` char(2) DEFAULT NULL COMMENT 'ISO 3166-1 alpha-2 pour drapeau fiche' AFTER `country_of_residence`;
