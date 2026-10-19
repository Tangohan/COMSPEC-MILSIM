-- Icônes des objets observés en jeu (config `picture`, texture .paa lue dans le PBO par COMSPEC Link « Item.Icons »
-- puis convertie en PNG par le site). Une ligne par communauté et par classe : `ok` avec l'empreinte du PNG
-- (storage/game-item-icons/<2 premiers>/<sha256>.png, partagé entre communautés), ou `not_found` / `invalid`
-- pour ne pas redemander l'icône avant 14 jours.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `game_item_icons` (
  `tenant_id` int unsigned NOT NULL,
  `class_name` varchar(128) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'ok',
  `icon_hash` char(64) DEFAULT NULL,
  `width` smallint unsigned DEFAULT NULL,
  `height` smallint unsigned DEFAULT NULL,
  `picture_path` varchar(255) NOT NULL DEFAULT '',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`,`class_name`),
  KEY `idx_game_item_icons_hash` (`tenant_id`,`icon_hash`),
  CONSTRAINT `fk_game_item_icons_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
