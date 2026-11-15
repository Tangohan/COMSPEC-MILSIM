-- Anciens noms : journal de la reprise « nom de personnage -> prénom et nom », lancée à la main par un responsable depuis
-- Qualité des données. Une ligne par membre repris : la valeur d'origine (nom de personnage), le prénom et le nom copiés,
-- et si la fiche d'état civil a été créée à cette occasion. Sert de marqueur « importé depuis l'ancien nom de personnage,
-- à vérifier » et de source au retour arrière d'un lot (batch_id). Aucune clé étrangère : le journal survit à tout.
-- Retour arrière de la table : migrations/rollback/legacy_identity_import_log_down.sql.

CREATE TABLE IF NOT EXISTS legacy_identity_import_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  batch_id VARCHAR(32) NOT NULL,
  actor_user_id INT UNSIGNED DEFAULT NULL,
  source_character_name VARCHAR(150) NOT NULL,
  first_name VARCHAR(100) DEFAULT NULL,
  last_name VARCHAR(100) DEFAULT NULL,
  identity_row_created TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reverted_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_legacy_identity_import_batch (tenant_id, batch_id),
  KEY idx_legacy_identity_import_user (tenant_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
