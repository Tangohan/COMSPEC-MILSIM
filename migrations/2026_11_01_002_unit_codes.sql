-- Codes unité : code lisible hiérarchique + identifiant administratif numérique.
-- Appliqué par l'étape unit_codes de la file active (run-migrations.php) ;
-- App\Repositories\UnitCodeRepository::ensureTables() reste un filet de sécurité ; idempotent.

CREATE TABLE IF NOT EXISTS `tenant_unit_codes` (
  `tenant_id` int unsigned NOT NULL,
  `community_code` varchar(12) NOT NULL COMMENT 'Code court de la communauté, unique sur la plateforme (ex. SOAR)',
  `admin_number` int unsigned NOT NULL COMMENT 'Numéro administratif stable, affiché sur 4 chiffres (0042)',
  `next_unit_number` int unsigned NOT NULL DEFAULT 1 COMMENT 'Prochain numéro d unité, ne redescend jamais',
  `is_custom` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`tenant_id`),
  UNIQUE KEY `uk_tuc_community_code` (`community_code`),
  UNIQUE KEY `uk_tuc_admin_number` (`admin_number`),
  CONSTRAINT `fk_tenant_unit_codes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `unit_codes` (
  `unit_id` int unsigned NOT NULL,
  `tenant_id` int unsigned NOT NULL,
  `segment` varchar(12) NOT NULL DEFAULT '' COMMENT 'Segment propre à l unité (B, A, 1…)',
  `code_path` varchar(191) NOT NULL COMMENT 'Code complet recalculé depuis l arbre (SOAR-B-A-1)',
  `admin_number` int unsigned NOT NULL COMMENT 'Numéro de l unité dans la communauté, jamais réattribué',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`unit_id`),
  UNIQUE KEY `uk_uc_tenant_path` (`tenant_id`, `code_path`),
  UNIQUE KEY `uk_uc_tenant_admin` (`tenant_id`, `admin_number`),
  CONSTRAINT `fk_unit_codes_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_unit_codes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
