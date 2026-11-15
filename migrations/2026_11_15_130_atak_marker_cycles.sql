-- Cycle de 24 h des marqueurs des postes web (poste TOC web, ATAK web / Overwatch, TACMAP) : à chaque bascule (12:00 par
-- défaut, réglable par communauté), les marqueurs et tracés du cycle écoulé sont archivés dans une « mission archivée »
-- consultable en lecture seule, puis retirés de la carte. Un élément épinglé reste affiché d'un cycle à l'autre.
-- Aucune donnée existante n'est modifiée par cette étape ; l'archivage lui-même est fait par la tâche planifiée
-- « atak_marker_cycle » (par lots, instantané écrit AVANT tout retrait). Sans ligne de réglage, le cycle est actif.
-- Retour arrière : migrations/rollback/atak_marker_cycles_down.sql.

CREATE TABLE IF NOT EXISTS atak_marker_cycle_settings (
  tenant_id INT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'cycle actif (sans ligne : actif) ; 0 = affichage cumulatif comme avant',
  switch_time CHAR(5) NOT NULL DEFAULT '12:00' COMMENT 'heure de bascule (HH:MM) dans le fuseau de la communauté',
  duration_days TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'durée d''un cycle en jours (1 = 24 h)',
  timezone VARCHAR(64) DEFAULT NULL COMMENT 'fuseau ; vide : celui de la communauté (tenants.default_timezone)',
  retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 1095 COMMENT 'conservation des missions archivées en jours ; 0 = indéfinie',
  updated_by INT UNSIGNED DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (tenant_id),
  CONSTRAINT fk_atak_marker_cycle_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS atak_marker_archives (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  map_id INT UNSIGNED NOT NULL DEFAULT 1,
  cycle_key VARCHAR(24) NOT NULL COMMENT 'identifiant du cycle (début en UTC, ex. 20261114T110000Z) ; « legacy » pour la reprise de l''existant',
  label VARCHAR(160) NOT NULL COMMENT 'ex. Mission du 14/11 12:00 → 15/11 12:00',
  starts_at DATETIME NOT NULL COMMENT 'UTC',
  ends_at DATETIME NOT NULL COMMENT 'UTC',
  markers_count INT UNSIGNED NOT NULL DEFAULT 0,
  shapes_count INT UNSIGNED NOT NULL DEFAULT 0,
  source VARCHAR(10) NOT NULL DEFAULT 'auto' COMMENT 'auto|legacy',
  created_by INT UNSIGNED DEFAULT NULL COMMENT 'NULL : système',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_atak_marker_archive_cycle (tenant_id, map_id, cycle_key),
  KEY idx_atak_marker_archive_tenant_end (tenant_id, ends_at),
  CONSTRAINT fk_atak_marker_archives_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS atak_marker_archive_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  archive_id INT UNSIGNED NOT NULL,
  kind VARCHAR(6) NOT NULL COMMENT 'marker|shape',
  source_id INT UNSIGNED NOT NULL COMMENT 'identifiant d''origine : rejouer l''archivage ne crée jamais de doublon',
  item_at DATETIME DEFAULT NULL COMMENT 'dernière modification de l''élément (UTC)',
  payload MEDIUMTEXT NOT NULL COMMENT 'contenu complet de l''élément (JSON)',
  PRIMARY KEY (id),
  UNIQUE KEY uq_atak_marker_archive_item (archive_id, kind, source_id),
  CONSTRAINT fk_atak_marker_archive_items_archive FOREIGN KEY (archive_id) REFERENCES atak_marker_archives (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
