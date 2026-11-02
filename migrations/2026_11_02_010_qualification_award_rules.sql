-- Qualifications de toute nature : modes d’obtention (à la main, formation Academy, automatique sur seuils mesurés
-- en jeu), prérequis de grade et de fonction, visibilité et besoin par unité. Une ligne par qualification ; une
-- qualification sans ligne garde le fonctionnement d’avant (délivrée à la main, visible partout).
-- Exécuté par database/steps/3430_qualification_award_rules.php ; idempotent (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `qualification_award_rules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `definition_id` int unsigned NOT NULL COMMENT 'personnel_qualification_definitions.id',
  `allow_manual` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Délivrable à la main par un responsable',
  `auto_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Règle automatique active (seuils, formation Academy, qualification)',
  `auto_mode` varchar(12) NOT NULL DEFAULT 'direct' COMMENT 'direct = délivrée ; propose = à valider par un responsable',
  `rule_json` text DEFAULT NULL COMMENT 'Arbre toutes / au moins une de critères (QualificationCriteriaEngine)',
  `prereq_grade_id` int unsigned DEFAULT NULL COMMENT 'Grade minimum (grades.id)',
  `prereq_job_role_id` int unsigned DEFAULT NULL COMMENT 'Fonction demandée (personnel_job_roles.id)',
  `show_on_profile` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Affichée sur la page publique du joueur',
  `show_on_orbat` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Affichée sur la fiche opérateur ATAK',
  `min_holders_per_unit` smallint unsigned DEFAULT NULL COMMENT 'Besoin minimum de titulaires par unité',
  `last_evaluated_at` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qual_award_rules_definition` (`definition_id`),
  KEY `idx_qual_award_rules_tenant_auto` (`tenant_id`, `auto_enabled`),
  CONSTRAINT `fk_qual_award_rules_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_qual_award_rules_definition` FOREIGN KEY (`definition_id`) REFERENCES `personnel_qualification_definitions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
