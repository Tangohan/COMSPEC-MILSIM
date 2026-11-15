-- SSE « À trier » : état de traitement par élément (acquitté, en veille, assigné), règles automatiques « Cerbère-Tri » par
-- communauté et journal de leurs déclenchements. Aucune donnée existante n'est modifiée ; sans ces tables la page « À trier »
-- reste lisible en mode regroupé, sans acquittement persistant.
-- Les colonnes de déduplication de sse_engine_signals sont ajoutées par l'étape 4690 (SchemaInspector), pas ici.
-- Retour arrière : migrations/rollback/sse_triage_automatisms_down.sql.

CREATE TABLE IF NOT EXISTS sse_triage_state (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  item_type VARCHAR(32) NOT NULL COMMENT 'type de l''élément : signal, suggestion, note, check…',
  item_key VARCHAR(64) NOT NULL COMMENT 'identifiant de l''élément dans son type',
  status VARCHAR(16) NOT NULL DEFAULT 'open' COMMENT 'open|acknowledged|snoozed ; open après un rappel garde la trace pour que les règles ne le reprennent pas',
  snoozed_until DATETIME NULL,
  acknowledged_by INT UNSIGNED NULL,
  acknowledged_at DATETIME NULL,
  assigned_to INT UNSIGNED NULL,
  priority_boost TINYINT NOT NULL DEFAULT 0 COMMENT 'majoration de priorité posée par une règle',
  note VARCHAR(500) NULL COMMENT 'motif facultatif',
  rule_id INT UNSIGNED NULL COMMENT 'règle Cerbère-Tri qui a posé l''état, NULL si un humain',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_triage_item (tenant_id, item_type, item_key),
  KEY idx_sse_triage_status (tenant_id, status, snoozed_until),
  KEY idx_sse_triage_assigned (tenant_id, assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_triage_rules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  preset_key VARCHAR(32) NULL COMMENT 'modèle fourni dont la règle est issue',
  name VARCHAR(160) NOT NULL DEFAULT '',
  source_key VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'condition : file (anomalies, suggestions…), vide = toutes',
  type_key VARCHAR(80) NOT NULL DEFAULT '' COMMENT 'condition : type d''élément (signal:completeness…), vide = tous',
  keyword VARCHAR(80) NOT NULL DEFAULT '' COMMENT 'condition : mot du titre',
  min_age_days SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'condition : ancienneté minimale en jours',
  min_severity VARCHAR(10) NULL COMMENT 'condition : gravité minimale info|medium|high|critical',
  max_severity VARCHAR(10) NULL COMMENT 'condition : gravité maximale',
  scope VARCHAR(12) NOT NULL DEFAULT 'all' COMMENT 'portée : all, ou cover (sujets sous identité de couverture)',
  action VARCHAR(16) NOT NULL DEFAULT 'acknowledge' COMMENT 'acknowledge|snooze|priority|assign',
  action_value INT NOT NULL DEFAULT 0 COMMENT 'jours de veille, majoration, ou compte assigné',
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  last_fired_at DATETIME NULL,
  fired_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sse_triage_rules_tenant (tenant_id, enabled),
  KEY idx_sse_triage_rules_preset (tenant_id, preset_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_triage_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  batch_id VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'une action groupée ou un passage de règle = un lot, annulable d''un coup',
  item_type VARCHAR(32) NOT NULL,
  item_key VARCHAR(64) NOT NULL,
  action VARCHAR(24) NOT NULL COMMENT 'acknowledge|snooze|recall|assign|auto_acknowledge|auto_snooze|auto_priority|auto_assign|undo',
  actor_user_id INT UNSIGNED NULL,
  actor_label VARCHAR(160) NOT NULL DEFAULT '',
  rule_id INT UNSIGNED NULL,
  before_json TEXT NULL,
  after_json TEXT NULL,
  note VARCHAR(500) NULL,
  undone_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sse_triage_events_tenant (tenant_id, id),
  KEY idx_sse_triage_events_batch (tenant_id, batch_id),
  KEY idx_sse_triage_events_item (tenant_id, item_type, item_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
