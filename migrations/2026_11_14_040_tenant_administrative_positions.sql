-- Positions administratives créées par une communauté, en plus des quatre du site (En formation, En service actif,
-- En service non actif, En service statutaire de renforcement). Un membre porte une seule position à la fois : elle reste
-- stockée comme un rôle d'affichage (roles.semantic_tier = 'status') au slug « status_custom_… » de la ligne ci-dessous.

CREATE TABLE IF NOT EXISTS tenant_administrative_positions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  slug VARCHAR(64) NOT NULL,
  label VARCHAR(60) NOT NULL,
  summary VARCHAR(255) NOT NULL DEFAULT '',
  detail TEXT DEFAULT NULL,
  mission VARCHAR(60) NOT NULL DEFAULT '',
  service VARCHAR(60) NOT NULL DEFAULT '',
  strength VARCHAR(60) NOT NULL DEFAULT '',
  tone VARCHAR(12) NOT NULL DEFAULT 'muted',
  display_order INT NOT NULL DEFAULT 0,
  archived_at DATETIME DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tenant_admin_position (tenant_id, slug),
  CONSTRAINT fk_tenant_admin_positions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
