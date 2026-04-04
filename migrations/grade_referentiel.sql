-- Référentiel de grades multi-doctrine (FR / US, classique / OTAN)
-- Tables globales (sans tenant_id). La table grades existante reste en place jusqu'à la migration des données.
-- Exécution incrémentale via run-migrations.php

SET NAMES utf8mb4;

-- grade_categories : Officier, Sous-officier, MDR, Civil, Hors grades
CREATE TABLE IF NOT EXISTS grade_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL,
  label VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY code (code),
  KEY is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- grade_systems : FR_CLASSIC, US_CLASSIC (format_type classic) ; optionnel OTAN par pays
CREATE TABLE IF NOT EXISTS grade_systems (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL,
  label VARCHAR(100) NOT NULL,
  country_code VARCHAR(10) NOT NULL,
  format_type ENUM('classic','otan') NOT NULL DEFAULT 'classic',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY code (code),
  KEY country_code (country_code),
  KEY is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- grades_referentiel : nouveau référentiel (remplacera l'ancienne table grades après migration)
CREATE TABLE IF NOT EXISTS grades_referentiel (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  grade_system_id BIGINT UNSIGNED NOT NULL,
  grade_category_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(50) NOT NULL,
  label_short VARCHAR(100) NOT NULL,
  label_long VARCHAR(150) NOT NULL,
  label_otan VARCHAR(50) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_commissioned TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY grade_system_id (grade_system_id),
  KEY grade_category_id (grade_category_id),
  KEY sort_order (sort_order),
  KEY is_active (is_active),
  CONSTRAINT fk_grades_ref_system FOREIGN KEY (grade_system_id) REFERENCES grade_systems (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_grades_ref_category FOREIGN KEY (grade_category_id) REFERENCES grade_categories (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
