-- Cerbère-Lecture (détections du bureau SSE) : réglages des règles du lexique par communauté et décisions prises sur les
-- propositions (appliquée, ignorée, mauvaise détection, retirée par la re-détection). Aucune donnée existante n'est modifiée ;
-- sans ces tables le lexique livré (config/sse_detection_lexicon.php) s'applique et les propositions restent applicables.
-- Retour arrière : migrations/rollback/sse_detection_rules_down.sql.

CREATE TABLE IF NOT EXISTS sse_detection_rule_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  rule_id VARCHAR(12) NOT NULL COMMENT 'identifiant de la règle du lexique livré (R-14, U-01, K-01…)',
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  weight TINYINT UNSIGNED NULL COMMENT 'poids réglé 0 à 100, NULL = poids livré',
  terms_json TEXT NULL COMMENT 'termes réglés {terme: poids|null}, NULL = termes livrés',
  exclusions_json TEXT NULL COMMENT 'exclusions réglées, NULL = exclusions livrées',
  requires_json TEXT NULL COMMENT 'contexte requis réglé, NULL = contexte livré',
  updated_by INT UNSIGNED NULL,
  updated_by_label VARCHAR(160) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_detection_rule (tenant_id, rule_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_detection_decisions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  source VARCHAR(12) NOT NULL COMMENT 'fiche ou cr',
  source_id INT UNSIGNED NOT NULL,
  target VARCHAR(12) NOT NULL COMMENT 'theme, urgency ou kind',
  value VARCHAR(24) NOT NULL COMMENT 'code proposé (SECUR, critique, FRT…)',
  rule_ids VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'règles qui ont fait la proposition, séparées par des virgules',
  confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
  decision VARCHAR(12) NOT NULL COMMENT 'applied|ignored|rejected|withdrawn',
  reason VARCHAR(200) NULL COMMENT 'motif d''une mauvaise détection ou du retrait',
  lexicon_version VARCHAR(24) NOT NULL DEFAULT '',
  actor_user_id INT UNSIGNED NULL,
  actor_label VARCHAR(160) NOT NULL DEFAULT '',
  decided_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_detection_decision (tenant_id, source, source_id, target, value),
  KEY idx_sse_detection_decided (tenant_id, decided_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
