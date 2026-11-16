-- Effectifs : vérifications de disponibilité guidées (fiche d'un membre ou sélection du tableur). UNE table NOUVELLE :
-- une ligne par vérification enregistrée par un responsable (qui, quand, conclusion proposée, conclusion retenue, note,
-- suite donnée et données relevées au moment de la vérification). Aucune colonne ajoutée ni donnée modifiée ailleurs.
-- Sans cette table, la vérification se lance et s'affiche quand même ; seul l'enregistrement est refusé avec un message.
-- Aucune unicité : un membre peut être vérifié plusieurs fois. Retour arrière :
-- migrations/rollback/member_availability_checks_down.sql.

CREATE TABLE IF NOT EXISTS member_availability_checks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL COMMENT 'membre vérifié',
  checked_by INT UNSIGNED NULL COMMENT 'responsable qui a validé',
  proposed VARCHAR(20) NOT NULL COMMENT 'conclusion proposée par les étapes : available, low, absent, inactive',
  conclusion VARCHAR(20) NOT NULL COMMENT 'conclusion retenue par le responsable',
  corrected TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 : le responsable a corrigé la conclusion proposée',
  note TEXT NULL,
  follow_up VARCHAR(20) NOT NULL DEFAULT 'none' COMMENT 'none, recheck, rh, position',
  follow_up_due DATE NULL,
  follow_up_ref VARCHAR(80) NULL COMMENT 'étape du suivi du dossier créée pour la suite',
  facts_json MEDIUMTEXT NULL COMMENT 'étapes et valeurs relevées au moment de la vérification',
  batch_id VARCHAR(32) NULL COMMENT 'vérification groupée depuis le tableur',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_mac_member (tenant_id, user_id, created_at),
  KEY idx_mac_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
