-- SSE Personnes : dernière ouverture de chaque fiche par chaque utilisateur (une ligne par communauté, compte et personne).
-- « Dernière lecture » d'une fiche = la plus récente de ces lignes ; « dernière ouverture par vous » = la ligne de votre compte.
-- Retour arrière : migrations/rollback/sse_persons_work_down.sql.
CREATE TABLE IF NOT EXISTS sse_person_views (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  person_id INT UNSIGNED NOT NULL,
  last_opened_at DATETIME NOT NULL,
  open_count INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sse_person_views (tenant_id, user_id, person_id),
  KEY idx_sse_person_views_person (tenant_id, person_id, last_opened_at),
  CONSTRAINT fk_sse_person_views_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
