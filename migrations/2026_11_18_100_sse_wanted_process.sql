-- SSE avis de recherche : service demandeur (code du catalogue ou de la communauté), classification, renouvellements, circuit de
-- validation et service lévant. UNE table NOUVELLE, fille de sse_wanted_notices : aucune colonne ajoutée ni donnée modifiée ; un avis
-- sans ligne ici fonctionne comme avant (service « Autre », diffusé d'office). Les services créés par la communauté, l'ordre, le
-- service par défaut et l'option de validation sont rangés dans ses réglages (sse_wanted_settings.settings_json) : aucune table de plus.
-- Sans cette table, le formulaire place l'avis avec le texte du service seul et l'écrit clairement.
-- Retour arrière : migrations/rollback/sse_wanted_process_down.sql.

CREATE TABLE IF NOT EXISTS sse_wanted_notice_meta (
  notice_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  service_code VARCHAR(30) NULL COMMENT 'SVC-… (catalogue) ou CSV-… (communauté) ; vide : service saisi à la main',
  service_json TEXT NULL COMMENT 'instantané du service au jour de la pose : nom, sigle, coordonnées, couleur',
  classification VARCHAR(20) NULL COMMENT 'niveau de classification de l''avis (échelle SSE)',
  parent_notice_id INT UNSIGNED NULL COMMENT 'avis renouvelé par celui-ci',
  validation_state VARCHAR(10) NOT NULL DEFAULT 'live' COMMENT 'pending|live|rejected',
  submitted_by_user_id INT UNSIGNED NULL,
  validated_by_user_id INT UNSIGNED NULL,
  validated_at DATETIME NULL,
  validation_reason VARCHAR(500) NULL,
  closing_service_code VARCHAR(30) NULL COMMENT 'service qui lève ou clôt l''avis',
  history_json TEXT NULL COMMENT 'versions : prolongations avec motif',
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (notice_id),
  KEY idx_sse_wanted_meta_service (tenant_id, service_code),
  KEY idx_sse_wanted_meta_state (tenant_id, validation_state),
  KEY idx_sse_wanted_meta_parent (tenant_id, parent_notice_id),
  CONSTRAINT fk_sse_wanted_meta_notice FOREIGN KEY (notice_id) REFERENCES sse_wanted_notices (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
