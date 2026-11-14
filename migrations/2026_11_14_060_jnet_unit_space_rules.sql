-- JNET : réglages par espace d'unité (prise en compte obligatoire des ordres importants, relais automatique aux
-- sous-unités, publication réservée), unités autorisées à voir l'espace avec un droit par ligne, gestionnaires désignés
-- (« chefs de module »), et journal des relais automatiques (un ordre n'est relayé qu'une fois par espace).
-- Les colonnes require_ack, auto_relay et post_min de jnet_unit_spaces sont ajoutées par l'étape jnet_unit_space_rules
-- (database/steps/4540_jnet_unit_space_rules.php), seulement si elles manquent. Retour arrière :
-- migrations/rollback/jnet_unit_space_rules_down.sql.

CREATE TABLE IF NOT EXISTS jnet_unit_space_access (
  tenant_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NOT NULL COMMENT 'espace concerné',
  grantee_unit_id INT UNSIGNED NOT NULL COMMENT 'unité autorisée (0 = toute l''organisation)',
  level VARCHAR(8) NOT NULL DEFAULT 'read' COMMENT 'read|write|lead',
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tenant_id, unit_id, grantee_unit_id),
  CONSTRAINT fk_jnet_unit_space_access_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jnet_unit_space_managers (
  tenant_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tenant_id, unit_id, user_id),
  KEY idx_jnet_unit_space_managers_user (tenant_id, user_id),
  CONSTRAINT fk_jnet_unit_space_managers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jnet_unit_auto_relays (
  tenant_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NOT NULL COMMENT 'espace qui a relayé',
  exchange_id INT UNSIGNED NOT NULL COMMENT 'échange d''origine relayé',
  relay_exchange_id INT UNSIGNED DEFAULT NULL COMMENT 'échange créé par le relais',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tenant_id, unit_id, exchange_id),
  KEY idx_jnet_unit_auto_relays_relay (relay_exchange_id),
  CONSTRAINT fk_jnet_unit_auto_relays_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
