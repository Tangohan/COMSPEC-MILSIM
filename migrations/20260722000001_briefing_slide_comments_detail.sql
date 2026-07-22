-- Diapositives de briefing : détail a posteriori + commentaires opérateurs.
-- Préférer bootstrap/tactical_briefing_slide_enrichment_migration.php (idempotent).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tactical_briefing_slide_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `slide_id` int unsigned NOT NULL,
  `author_label` varchar(120) NOT NULL DEFAULT '',
  `body` text NOT NULL,
  `source` enum('phone','admin','arma') NOT NULL DEFAULT 'phone',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tbsc_slide_created` (`slide_id`, `created_at`),
  KEY `idx_tbsc_tenant_created` (`tenant_id`, `created_at`),
  CONSTRAINT `tbsc_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tbsc_slide_fk` FOREIGN KEY (`slide_id`) REFERENCES `tactical_briefing_slides` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colonne detail_text : exécutée séparément via le bootstrap PHP si absente.
