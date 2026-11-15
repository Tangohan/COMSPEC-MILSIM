-- Paquet d'intégration : ce que reçoit un nouveau membre quand le recrutement l'intègre (grade, unité, emploi, niveaux
-- d'accès, qualifications d'office, formations à suivre, mot d'accueil), paramétré par communauté et, au besoin, par offre
-- ou par unité. Les valeurs renvoient aux référentiels existants (grades, rôles, unités, emplois, qualifications,
-- formations) : aucune nouvelle taxonomie. Sans paquet défini, l'intégration se passe comme avant. La table des
-- applications garde, par candidature, ce qui a été appliqué (rejouable sans doublon) et l'envoi du récapitulatif.
-- Retour arrière : migrations/rollback/member_integration_packages_down.sql.

CREATE TABLE IF NOT EXISTS member_integration_packages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'paquet retenu quand aucune offre ni unité ne correspond',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  opening_id INT UNSIGNED DEFAULT NULL COMMENT 'offre de recrutement à laquelle le paquet répond (candidat pilote : paquet pilote)',
  match_unit_id INT UNSIGNED DEFAULT NULL COMMENT 'unité de l''offre ou choisie à l''intégration à laquelle le paquet répond',
  grade_id INT UNSIGNED DEFAULT NULL,
  unit_id INT UNSIGNED DEFAULT NULL COMMENT 'unité d''affectation proposée (modifiable à l''intégration)',
  personnel_job_role_id INT UNSIGNED DEFAULT NULL,
  role_ids_json TEXT DEFAULT NULL COMMENT 'niveaux d''accès, liste d''identifiants de rôles',
  welcome_message TEXT DEFAULT NULL COMMENT 'consignes de bienvenue envoyées au nouveau membre',
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mi_packages_tenant (tenant_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_integration_package_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  package_id INT UNSIGNED NOT NULL,
  item_type VARCHAR(16) NOT NULL COMMENT 'qualification|course',
  ref_id INT UNSIGNED NOT NULL COMMENT 'définition de qualification ou formation',
  status VARCHAR(16) NOT NULL DEFAULT 'obtained' COMMENT 'qualification : obtained (délivrée) | in_training (en formation)',
  valid_months SMALLINT UNSIGNED DEFAULT NULL COMMENT 'validité propre au paquet, sinon celle de la qualification',
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mi_package_item (package_id, item_type, ref_id),
  KEY idx_mi_package_items_tenant (tenant_id, package_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_integration_applications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  enlistment_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  package_id INT UNSIGNED DEFAULT NULL,
  actor_user_id INT UNSIGNED NOT NULL,
  applied_json MEDIUMTEXT DEFAULT NULL COMMENT 'ce qui a été appliqué, par rubrique',
  welcome_sent_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mi_application_enlistment (tenant_id, enlistment_id),
  KEY idx_mi_applications_user (tenant_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
