-- Avancement : suivi des propositions automatiques d'une campagne (retenue = candidature créée par un responsable,
-- écartée = masquée de la liste, avec le motif). Une ligne par membre et par campagne (uk_adv_proposal_campaign_person).
-- Exécuté par database/steps/3385_advancement_proposal_decisions.php ; idempotent.

CREATE TABLE IF NOT EXISTS `advancement_proposal_decisions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `campaign_id` int unsigned NOT NULL COMMENT 'advancement_campaigns.id',
  `personnel_id` int unsigned NOT NULL COMMENT 'users.id',
  `decision` varchar(16) NOT NULL COMMENT 'retenue | ecartee',
  `score` smallint unsigned NOT NULL DEFAULT 0 COMMENT 'Score de la détection au moment de la décision (sur 100)',
  `justification` text DEFAULT NULL COMMENT 'Justification (retenue) ou motif (écartée)',
  `decided_by` int unsigned DEFAULT NULL,
  `decided_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_adv_proposal_campaign_person` (`campaign_id`, `personnel_id`),
  KEY `idx_adv_proposal_tenant` (`tenant_id`),
  CONSTRAINT `fk_adv_proposal_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
