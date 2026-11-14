-- Moteur de circuits unique (lot 1) : un circuit par objet existant (élévation, mobilité…), et son journal.
-- Les tables métier gardent leurs données ; ces deux tables ne portent que l'état du circuit et l'historique.

CREATE TABLE IF NOT EXISTS workflow_instances (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  subject_type VARCHAR(40) NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  state VARCHAR(32) NOT NULL DEFAULT 'decision_pending',
  member_user_id INT UNSIGNED DEFAULT NULL,
  unit_id INT UNSIGNED DEFAULT NULL,
  assignee_user_id INT UNSIGNED DEFAULT NULL,
  task_id BIGINT UNSIGNED DEFAULT NULL,
  opened_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL,
  closed_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_workflow_subject (tenant_id, subject_type, subject_id),
  KEY idx_workflow_assignee (tenant_id, assignee_user_id, state),
  KEY idx_workflow_member (tenant_id, member_user_id),
  CONSTRAINT fk_workflow_instances_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workflow_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  instance_id BIGINT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  action VARCHAR(40) NOT NULL,
  from_state VARCHAR(32) DEFAULT NULL,
  to_state VARCHAR(32) DEFAULT NULL,
  actor_user_id INT UNSIGNED DEFAULT NULL,
  comment TEXT DEFAULT NULL,
  payload_json TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_workflow_events_instance (instance_id, id),
  KEY idx_workflow_events_tenant (tenant_id, created_at),
  CONSTRAINT fk_workflow_events_instance FOREIGN KEY (instance_id) REFERENCES workflow_instances (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
