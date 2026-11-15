-- Portail SSE : droits d'accès paramétrables par communauté. Une règle accorde (allow) ou refuse (deny) un niveau de
-- droit (existence, lecture, écriture, versement, suppression, export, gestion) sur une section du portail à un
-- bénéficiaire : niveau d'accès, unité, unité et sous-unités, ou membre. Sans règle, les gardes d'origine s'appliquent
-- telles quelles ; une règle ne donne jamais l'entrée au portail (droit d'entrer et confidentialité acceptée restent
-- exigés) et un refus explicite l'emporte. Les règles retirées restent en base (revoked_at) ; l'historique est conservé
-- à part (sse_access_rule_history). Retour arrière : migrations/rollback/sse_access_rules_down.sql.

CREATE TABLE IF NOT EXISTS sse_access_rules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  section VARCHAR(32) NOT NULL COMMENT 'fichiers|photos|papiers|extractions|dossiers|analyse|lore|journal|terrain|outils',
  level VARCHAR(16) NOT NULL COMMENT 'exists|read|write|add|delete|export|manage',
  subject_type VARCHAR(12) NOT NULL COMMENT 'role|unit|unit_tree|user',
  subject_id INT UNSIGNED NOT NULL,
  effect VARCHAR(8) NOT NULL COMMENT 'allow|deny',
  starts_at DATETIME DEFAULT NULL,
  ends_at DATETIME DEFAULT NULL,
  reason VARCHAR(500) NOT NULL DEFAULT '',
  source VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'modèle appliqué, vide si saisie à la main',
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME DEFAULT NULL,
  revoked_by INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_sse_access_rules_tenant (tenant_id, revoked_at, section, level),
  KEY idx_sse_access_rules_subject (tenant_id, subject_type, subject_id, revoked_at),
  CONSTRAINT fk_sse_access_rules_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_access_rule_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  rule_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(16) NOT NULL COMMENT 'create|revoke|template',
  actor_user_id INT UNSIGNED NOT NULL,
  actor_label VARCHAR(120) NOT NULL DEFAULT '',
  section VARCHAR(32) NOT NULL DEFAULT '',
  level VARCHAR(16) NOT NULL DEFAULT '',
  subject_type VARCHAR(12) NOT NULL DEFAULT '',
  subject_id INT UNSIGNED NOT NULL DEFAULT 0,
  subject_label VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'libellé au moment du changement (le bénéficiaire peut disparaître)',
  effect VARCHAR(8) NOT NULL DEFAULT '',
  starts_at DATETIME DEFAULT NULL,
  ends_at DATETIME DEFAULT NULL,
  reason VARCHAR(500) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sse_access_rule_history_tenant (tenant_id, id),
  KEY idx_sse_access_rule_history_rule (tenant_id, rule_id),
  CONSTRAINT fk_sse_access_rule_history_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
