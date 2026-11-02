-- Émetteurs de qualifications : membres et fonctions habilités (instructeurs, signataires du brevet).
-- Exécuté par database/steps/3470_qualification_issuers_enrichment.php ; idempotent (CREATE TABLE IF NOT EXISTS).
-- Une habilitation désigne soit un membre (user_id), soit une fonction (job_role_id) ; aucune délivrance passée
-- n’en dépend : retirer une habilitation ne touche à aucune qualification déjà délivrée.

CREATE TABLE IF NOT EXISTS `qualification_issuer_authorizations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `issuer_id` int unsigned NOT NULL COMMENT 'qualification_issuers.id',
  `user_id` int unsigned DEFAULT NULL COMMENT 'Membre habilité (users.id)',
  `job_role_id` int unsigned DEFAULT NULL COMMENT 'Fonction habilitée (personnel_job_roles.id)',
  `role` varchar(16) NOT NULL DEFAULT 'instructor' COMMENT 'instructor = instructeur ; signatory = signe les brevets',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qual_issuer_auth_issuer` (`tenant_id`, `issuer_id`),
  KEY `idx_qual_issuer_auth_user` (`user_id`),
  CONSTRAINT `fk_qual_issuer_auth_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_qual_issuer_auth_issuer` FOREIGN KEY (`issuer_id`) REFERENCES `qualification_issuers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
