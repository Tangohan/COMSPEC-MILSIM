-- Suivi du dossier d'un membre (Effectifs > fiche > Suivi du dossier) : étapes personnalisées ajoutées par un responsable,
-- compléments posés sur une étape d'un autre parcours (échéance reportée, responsable changé, note interne) et historique
-- de chaque changement (qui, quand, avant, après, motif). Les étapes des parcours existants (intégration, recrutement,
-- qualifications, documents, bilans, fiabilisation, carrière) restent dans leurs tables : cette étape n'en copie rien.
-- Un complément porte source_type et source_id de l'objet visé ; une étape personnalisée les laisse vides.
-- Retour arrière : migrations/rollback/member_dossier_steps_down.sql.

CREATE TABLE IF NOT EXISTS member_dossier_steps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  track VARCHAR(24) NOT NULL DEFAULT 'integration',
  title VARCHAR(180) NOT NULL DEFAULT '',
  status VARCHAR(16) NOT NULL DEFAULT 'todo',
  owner_user_id INT UNSIGNED DEFAULT NULL,
  due_on DATE DEFAULT NULL,
  done_at DATETIME DEFAULT NULL,
  note TEXT DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  source_type VARCHAR(32) DEFAULT NULL,
  source_id VARCHAR(64) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mds_source (tenant_id, user_id, source_type, source_id),
  KEY idx_mds_user (tenant_id, user_id),
  KEY idx_mds_due (tenant_id, status, due_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_dossier_step_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  step_key VARCHAR(80) NOT NULL,
  step_title VARCHAR(180) NOT NULL DEFAULT '',
  action VARCHAR(24) NOT NULL,
  actor_user_id INT UNSIGNED DEFAULT NULL,
  before_json TEXT DEFAULT NULL,
  after_json TEXT DEFAULT NULL,
  reason VARCHAR(500) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mdse_user (tenant_id, user_id, created_at),
  KEY idx_mdse_step (tenant_id, user_id, step_key, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
