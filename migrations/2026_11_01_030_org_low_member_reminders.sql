-- Communautés restées à un ou deux membres : journal des rappels envoyés aux responsables (un seul envoi par
-- palier, garanti par uk_olmr_tenant_stage) et suivi de l'administration de la plateforme (entrée dans la liste
-- « Organisations à supprimer », alerte envoyée, communauté gardée).
-- Exécuté par database/steps/3310_org_low_member_reminders.php ; idempotent.

CREATE TABLE IF NOT EXISTS `org_low_member_reminders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `stage` varchar(16) NOT NULL COMMENT 'Palier : m3 (3 mois) | m4 (relance à 4 mois)',
  `member_count` smallint unsigned NOT NULL DEFAULT 0 COMMENT 'Membres actifs au moment du rappel',
  `recipients` smallint unsigned NOT NULL DEFAULT 0 COMMENT 'Responsables prévenus',
  `emails` smallint unsigned NOT NULL DEFAULT 0 COMMENT 'E-mails partis (préférences respectées)',
  `sent_at` datetime NOT NULL COMMENT 'UTC',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_olmr_tenant_stage` (`tenant_id`, `stage`),
  CONSTRAINT `fk_olmr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `org_low_member_watch` (
  `tenant_id` int unsigned NOT NULL,
  `listed_at` datetime DEFAULT NULL COMMENT 'Entrée dans la liste « Organisations à supprimer » (UTC)',
  `admin_notified_at` datetime DEFAULT NULL COMMENT 'Notification site envoyée à l’administration',
  `admin_emailed_at` datetime DEFAULT NULL COMMENT 'Comprise dans le résumé e-mail hebdomadaire',
  `kept_at` datetime DEFAULT NULL COMMENT 'Gardée par l’administration : plus de rappel ni de liste',
  `kept_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`tenant_id`),
  KEY `idx_olmw_kept` (`kept_at`),
  CONSTRAINT `fk_olmw_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_olmw_kept_by` FOREIGN KEY (`kept_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
