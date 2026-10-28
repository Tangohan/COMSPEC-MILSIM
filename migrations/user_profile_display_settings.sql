-- Préférences d'affichage de la fiche personnelle (visibilité).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `user_profile_display_settings` (
  `user_id` int unsigned NOT NULL,
  `fiche_show_email_to_others` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = soi + staff seulement',
  `fiche_show_matricule_to_others` tinyint(1) NOT NULL DEFAULT 1,
  `public_roster_opt_in` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Apparaître sur le roster public /c/{slug} (opt-in)',
  `hide_personal_info` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = bloc identité civile réservé admin fiche + responsables communication',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_profile_display_settings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
