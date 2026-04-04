-- Table site_settings (paramètres par tenant, ex. forum_*)
-- À exécuter si la table n'existe pas (ex. base créée avant son ajout au schema).
-- Exemple : mysql -u ... -p ... < migrations/site_settings.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_key` (`tenant_id`,`key`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `site_settings_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
