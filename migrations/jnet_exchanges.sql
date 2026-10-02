-- JNET : échanges entre espaces (organisation, unités, équipes).
-- Un échange part d'un espace (from_unit_id, 0 = Organisation) vers un ou plusieurs espaces destinataires.
-- Le sens (descendant / interne / montant) se déduit de l'ORBAT au moment de l'affichage.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `jnet_exchanges` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `from_unit_id` int unsigned NOT NULL DEFAULT 0 COMMENT '0 = Organisation',
  `author_user_id` int unsigned NOT NULL,
  `kind` varchar(24) NOT NULL DEFAULT 'info' COMMENT 'ordre|compte_rendu|renseignement|document|info',
  `title` varchar(160) NOT NULL,
  `body` text NULL,
  `link_url` varchar(500) NULL COMMENT 'Lien interne optionnel (document, fiche, position)',
  `requires_ack` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = accusé de lecture demandé',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `jnet_exchanges_tenant_created` (`tenant_id`, `created_at`),
  KEY `jnet_exchanges_from` (`tenant_id`, `from_unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `jnet_exchange_targets` (
  `exchange_id` int unsigned NOT NULL,
  `unit_id` int unsigned NOT NULL COMMENT '0 = Organisation (tous les membres)',
  PRIMARY KEY (`exchange_id`, `unit_id`),
  KEY `jnet_exchange_targets_unit` (`unit_id`),
  CONSTRAINT `jnet_exchange_targets_exchange_fk` FOREIGN KEY (`exchange_id`) REFERENCES `jnet_exchanges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `jnet_exchange_reads` (
  `exchange_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `read_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`exchange_id`, `user_id`),
  KEY `jnet_exchange_reads_user` (`user_id`),
  CONSTRAINT `jnet_exchange_reads_exchange_fk` FOREIGN KEY (`exchange_id`) REFERENCES `jnet_exchanges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
