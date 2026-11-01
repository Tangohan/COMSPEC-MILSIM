-- Libellés générés par Athena (unités et emplois des modèles, postes types, types d'unité) remplacés par une
-- communauté, par langue (Back-office > Catalogue de l'organisation > Libellés). Sans ligne : traduction du site,
-- sinon libellé par défaut. Exécuté par database/steps/3290_tenant_generated_labels.php ; idempotent.

CREATE TABLE IF NOT EXISTS `tenant_generated_labels` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `label_key` varchar(160) NOT NULL COMMENT 'Clé lang/fr/unit_labels.php : type.identifiant',
  `lang` varchar(8) NOT NULL COMMENT 'fr | en',
  `label` varchar(255) NOT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_generated_labels_key` (`tenant_id`, `label_key`, `lang`),
  CONSTRAINT `fk_tenant_generated_labels_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
