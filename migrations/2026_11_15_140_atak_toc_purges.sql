-- Suppression douce et purge de la carte du poste TOC web : journal et état des purges par lots (une ligne par purge).
-- Les colonnes deleted_at, deleted_by et delete_batch des tables atak_markers et atak_map_shapes sont ajoutées par
-- l'étape database/steps/4780_atak_toc_soft_delete.php (ALTER TABLE contrôlé : compatible sqlite, rejouable).
-- Aucune donnée existante n'est modifiée. Sans cette étape, le poste retombe en mode dégradé : suppression définitive
-- avec confirmation, purge désactivée.
-- Retour arrière : migrations/rollback/atak_toc_purges_down.sql.

CREATE TABLE IF NOT EXISTS atak_toc_purges (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  map_id INT UNSIGNED NOT NULL DEFAULT 1,
  user_id INT UNSIGNED DEFAULT NULL COMMENT 'membre qui a lancé la purge',
  batch CHAR(16) NOT NULL COMMENT 'lot de suppression douce : annulation pendant 15 minutes',
  status VARCHAR(12) NOT NULL DEFAULT 'archiving' COMMENT 'archiving|deleting|done|failed|cancelled',
  scope_json TEXT NOT NULL COMMENT 'périmètre : types, auteurs, âge, zone, cycle',
  archive_first TINYINT(1) NOT NULL DEFAULT 1,
  keep_pinned TINYINT(1) NOT NULL DEFAULT 1,
  total INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'éléments concernés (aperçu confirmé)',
  archived INT UNSIGNED NOT NULL DEFAULT 0,
  deleted INT UNSIGNED NOT NULL DEFAULT 0,
  kept_pinned INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'éléments épinglés conservés',
  cursor_pos INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'prochain élément à traiter dans la phase en cours',
  archive_id INT UNSIGNED DEFAULT NULL COMMENT 'mission archivée créée avant la suppression',
  refs MEDIUMTEXT NOT NULL COMMENT 'éléments retenus à l''aperçu (JSON compact), 20000 au plus',
  error VARCHAR(80) DEFAULT NULL,
  locked_until DATETIME DEFAULT NULL COMMENT 'verrou d''un lot en cours (une seule exécution à la fois)',
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  finished_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_atak_toc_purges_scope (tenant_id, map_id, status),
  KEY idx_atak_toc_purges_batch (tenant_id, batch),
  CONSTRAINT fk_atak_toc_purges_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
