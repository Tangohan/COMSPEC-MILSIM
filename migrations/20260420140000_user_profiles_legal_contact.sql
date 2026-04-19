-- Champs complémentaires identité légale / contact (inscription, profil)
SET NAMES utf8mb4;

ALTER TABLE `user_profiles`
  ADD COLUMN `country_of_residence` varchar(100) DEFAULT NULL AFTER `nationality`,
  ADD COLUMN `discord_handle` varchar(120) DEFAULT NULL AFTER `country_of_residence`;
