-- Avancement de grade ATHENA : référentiel tenant, historique et campagnes.
-- Les suppressions d'historique sont volontairement interdites par le modèle applicatif.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS grade_filiere_definitions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  code VARCHAR(50) NOT NULL,
  label VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_grade_filiere_tenant_code (tenant_id, code),
  KEY idx_grade_filiere_tenant_order (tenant_id, sort_order),
  CONSTRAINT fk_grade_filiere_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grade_definitions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  code VARCHAR(50) NOT NULL,
  label VARCHAR(150) NOT NULL,
  short_label VARCHAR(50) NOT NULL,
  filiere_id BIGINT UNSIGNED NULL,
  rank_order INT NOT NULL,
  advancement_seniority_enabled TINYINT(1) NOT NULL DEFAULT 0,
  advancement_choice_enabled TINYINT(1) NOT NULL DEFAULT 0,
  min_time_in_previous_grade_months INT UNSIGNED NULL,
  required_qualification_id INT UNSIGNED NULL,
  required_qualification_level_id INT UNSIGNED NULL,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_grade_definition_tenant_code (tenant_id, code),
  UNIQUE KEY uq_grade_definition_tenant_order (tenant_id, filiere_id, rank_order),
  KEY idx_grade_definition_tenant_active (tenant_id, archived_at, rank_order),
  CONSTRAINT fk_grade_definition_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_grade_definition_filiere FOREIGN KEY (filiere_id) REFERENCES grade_filiere_definitions (id) ON DELETE RESTRICT,
  CONSTRAINT fk_grade_definition_qualification FOREIGN KEY (required_qualification_id) REFERENCES personnel_qualification_definitions (id) ON DELETE SET NULL,
  CONSTRAINT fk_grade_definition_qualification_level FOREIGN KEY (required_qualification_level_id) REFERENCES qualification_levels (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS advancement_campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  grade_id BIGINT UNSIGNED NOT NULL,
  filiere_id BIGINT UNSIGNED NULL,
  year SMALLINT UNSIGNED NOT NULL,
  opens_at DATE NOT NULL,
  closes_at DATE NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  quota_slots INT UNSIGNED NULL,
  published_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_advancement_campaign_tenant (tenant_id, year, status),
  CONSTRAINT fk_advancement_campaign_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_advancement_campaign_grade FOREIGN KEY (grade_id) REFERENCES grade_definitions (id) ON DELETE RESTRICT,
  CONSTRAINT fk_advancement_campaign_filiere FOREIGN KEY (filiere_id) REFERENCES grade_filiere_definitions (id) ON DELETE RESTRICT,
  CONSTRAINT fk_advancement_campaign_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS advancement_candidacies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id BIGINT UNSIGNED NOT NULL,
  personnel_id INT UNSIGNED NOT NULL,
  volunteered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_eligible TINYINT(1) NOT NULL DEFAULT 0,
  eligibility_reason TEXT NULL,
  eligibility_checked_at DATETIME NULL,
  preference_rank INT UNSIGNED NULL,
  commission_opinion VARCHAR(24) NULL,
  decision VARCHAR(24) NULL,
  decided_at DATE NULL,
  mobility_requested TINYINT(1) NOT NULL DEFAULT 0,
  requested_billet_id INT UNSIGNED NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_advancement_candidacy_person (campaign_id, personnel_id),
  KEY idx_advancement_candidacy_ranking (campaign_id, preference_rank),
  CONSTRAINT fk_advancement_candidacy_campaign FOREIGN KEY (campaign_id) REFERENCES advancement_campaigns (id) ON DELETE CASCADE,
  CONSTRAINT fk_advancement_candidacy_personnel FOREIGN KEY (personnel_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_advancement_candidacy_billet FOREIGN KEY (requested_billet_id) REFERENCES orbat_billets (id) ON DELETE SET NULL,
  CONSTRAINT fk_advancement_candidacy_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personnel_grade_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  personnel_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  grade_id BIGINT UNSIGNED NOT NULL,
  obtained_at DATE NOT NULL,
  obtained_via VARCHAR(16) NOT NULL,
  candidacy_id BIGINT UNSIGNED NULL,
  ends_at DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_personnel_grade_start (tenant_id, personnel_id, grade_id, obtained_at),
  KEY idx_personnel_grade_current (tenant_id, personnel_id, ends_at, obtained_at),
  CONSTRAINT fk_personnel_grade_personnel FOREIGN KEY (personnel_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_personnel_grade_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_personnel_grade_definition FOREIGN KEY (grade_id) REFERENCES grade_definitions (id) ON DELETE RESTRICT,
  CONSTRAINT fk_personnel_grade_candidacy FOREIGN KEY (candidacy_id) REFERENCES advancement_candidacies (id) ON DELETE RESTRICT,
  CONSTRAINT fk_personnel_grade_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS advancement_commissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id BIGINT UNSIGNED NOT NULL,
  meeting_date DATE NOT NULL,
  minutes_document_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_advancement_commission_campaign (campaign_id),
  CONSTRAINT fk_advancement_commission_campaign FOREIGN KEY (campaign_id) REFERENCES advancement_campaigns (id) ON DELETE CASCADE,
  CONSTRAINT fk_advancement_commission_minutes FOREIGN KEY (minutes_document_id) REFERENCES documents (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS advancement_commission_members (
  commission_id BIGINT UNSIGNED NOT NULL,
  personnel_id INT UNSIGNED NOT NULL,
  role VARCHAR(16) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (commission_id, personnel_id),
  CONSTRAINT fk_advancement_commission_member_commission FOREIGN KEY (commission_id) REFERENCES advancement_commissions (id) ON DELETE CASCADE,
  CONSTRAINT fk_advancement_commission_member_personnel FOREIGN KEY (personnel_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW personnel_current_grades AS
SELECT h.*
FROM personnel_grade_history h
WHERE h.ends_at IS NULL;
