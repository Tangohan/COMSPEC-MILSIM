-- Dossiers d'intérêt SSE : historique champ par champ, éléments liés et abonnés (les colonnes de sse_interest_cases sont
-- ajoutées par l'étape, voir database/steps/4710_sse_interest_case_suite.php). Aucune donnée n'est ajoutée ni modifiée.
-- Retour arrière : migrations/rollback/sse_interest_case_suite_down.sql.

CREATE TABLE IF NOT EXISTS sse_interest_case_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  interest_case_id INT UNSIGNED NOT NULL,
  action VARCHAR(32) NOT NULL,
  field_key VARCHAR(40) DEFAULT NULL,
  old_value TEXT DEFAULT NULL,
  new_value TEXT DEFAULT NULL,
  reason VARCHAR(300) DEFAULT NULL,
  actor_user_id INT UNSIGNED DEFAULT NULL,
  actor_label VARCHAR(160) DEFAULT NULL,
  version_after INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sse_interest_hist_case (tenant_id, interest_case_id, id),
  CONSTRAINT fk_sse_interest_hist_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_interest_case_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  interest_case_id INT UNSIGNED NOT NULL,
  link_type VARCHAR(24) NOT NULL,
  target_id VARCHAR(40) NOT NULL DEFAULT '',
  label VARCHAR(200) NOT NULL,
  note VARCHAR(300) DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_sse_interest_link (interest_case_id, link_type, target_id),
  KEY idx_sse_interest_link_case (tenant_id, interest_case_id),
  KEY idx_sse_interest_link_target (tenant_id, link_type, target_id),
  CONSTRAINT fk_sse_interest_link_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sse_interest_case_watchers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  interest_case_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_sse_interest_watch (interest_case_id, user_id),
  KEY idx_sse_interest_watch_user (tenant_id, user_id),
  CONSTRAINT fk_sse_interest_watch_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
