-- Moteur de circuits (lot 4, module de formule « workflow_circuits ») : circuit d'avis composé par la communauté,
-- un par type de demande. Sans ligne, le circuit par défaut s'applique (avis du chef d'unité pour les élévations et
-- les mobilités, aucun avis pour le reste).

CREATE TABLE IF NOT EXISTS workflow_circuits (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  subject_type VARCHAR(40) NOT NULL,
  steps_json TEXT NOT NULL,
  updated_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_workflow_circuit (tenant_id, subject_type),
  CONSTRAINT fk_workflow_circuits_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
