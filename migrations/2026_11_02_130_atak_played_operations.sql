-- Parties détectées en jeu (clé de mission ATAK « nom@carte#AAAAMMJJHHMM ») : gestion par la communauté.
--  - atak_debrief_ops : réglages d'une partie (nom donné, retrait de la liste, et colonnes ajoutées par
--    bootstrap/atak_played_ops_migration.php : type, catégorie, ignorée, supprimée, rattachement) ;
--  - atak_op_types : types d'opération, avec une règle de détection (motif sur la mission, la carte ou le nom) ;
--  - atak_op_ignore_rules : règles « ignorer à l'avenir » (motif sur la mission ou la carte), désactivables ;
--  - atak_op_categories : catégories de la communauté (entraînement, opération, campagne…).
-- Aucune ligne créée. Exécuté par bootstrap/atak_played_ops_migration.php ; idempotent (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `atak_debrief_ops` (
  `tenant_id` int unsigned NOT NULL,
  `mission_key` varchar(190) NOT NULL COMMENT 'Session jouée (nom@carte#UTC)',
  `label` varchar(200) DEFAULT NULL COMMENT 'Nom donné par la communauté, vide = nom de la mission',
  `hidden_at` datetime DEFAULT NULL COMMENT 'Retirée de la liste du suivi (historique effacé)',
  `updated_by_user_id` int unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`, `mission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `atak_op_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#3b6ea5',
  `icon` varchar(32) NOT NULL DEFAULT 'flag',
  `match_field` varchar(12) NOT NULL DEFAULT 'mission' COMMENT 'mission, map ou name',
  `pattern` varchar(190) NOT NULL DEFAULT '' COMMENT 'Motif (* et ? acceptés), vide = jamais détecté seul',
  `category` varchar(80) DEFAULT NULL COMMENT 'Catégorie appliquée aux parties de ce type',
  `created_by_user_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_atak_op_types_tenant` (`tenant_id`, `archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `atak_op_ignore_rules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `match_field` varchar(12) NOT NULL DEFAULT 'mission' COMMENT 'mission ou map',
  `pattern` varchar(190) NOT NULL,
  `like_pattern` varchar(400) NOT NULL COMMENT 'Même motif pour SQL LIKE (exclusion des heures)',
  `note` varchar(200) DEFAULT NULL,
  `created_by_user_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `disabled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_atak_op_ignore_tenant` (`tenant_id`, `disabled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `atak_op_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_atak_op_categories` (`tenant_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
