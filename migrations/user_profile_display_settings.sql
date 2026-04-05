-- Préférences d'affichage public (forum, fiche personnelle) — pseudo forum et visibilité.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `user_profile_display_settings` (
  `user_id` int unsigned NOT NULL,
  `forum_alias` varchar(80) DEFAULT NULL COMMENT 'Pseudo dédié au forum',
  `forum_label_mode` varchar(32) NOT NULL DEFAULT 'display_name' COMMENT 'forum_alias|callsign|character_name|display_name',
  `show_matricule_forum` tinyint(1) NOT NULL DEFAULT 1,
  `show_grade_forum` tinyint(1) NOT NULL DEFAULT 1,
  `show_unit_forum` tinyint(1) NOT NULL DEFAULT 1,
  `show_bio_forum` tinyint(1) NOT NULL DEFAULT 1,
  `fiche_show_email_to_others` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = soi + staff seulement',
  `fiche_show_matricule_to_others` tinyint(1) NOT NULL DEFAULT 1,
  `public_roster_opt_in` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Apparaître sur le roster public /c/{slug} (opt-in)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_profile_display_settings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
