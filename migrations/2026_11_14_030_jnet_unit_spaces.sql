-- JNET : espace dédié de chaque unité, personnalisé par son chef ou par l'organisation (thème, couleur, devise, mot
-- d'accueil, insigne, bannière, écran d'entrée). Sans ligne, l'espace garde l'apparence par défaut et les couleurs de
-- l'ORBAT.

CREATE TABLE IF NOT EXISTS jnet_unit_spaces (
  tenant_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NOT NULL,
  theme VARCHAR(16) NOT NULL DEFAULT 'operations',
  accent_color CHAR(7) DEFAULT NULL,
  tagline VARCHAR(140) DEFAULT NULL,
  welcome_text TEXT DEFAULT NULL,
  access_label VARCHAR(40) DEFAULT NULL,
  emblem_path VARCHAR(255) DEFAULT NULL,
  banner_path VARCHAR(255) DEFAULT NULL,
  entry_screen TINYINT(1) NOT NULL DEFAULT 1,
  updated_by INT UNSIGNED DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (tenant_id, unit_id),
  CONSTRAINT fk_jnet_unit_spaces_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
