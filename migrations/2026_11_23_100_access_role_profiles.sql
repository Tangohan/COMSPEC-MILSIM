-- Niveaux d'accès (Effectifs > Accès, table roles) : fiche descriptive de chaque niveau. QUATRE tables NOUVELLES, aucune
-- colonne ajoutée à roles, aucune donnée modifiée. Tous les champs sont facultatifs : un niveau sans fiche s'affiche comme avant.
--  - access_role_profiles : abrégé (unique dans la communauté), description détaillée (texte riche simple), icône de la
--    bibliothèque des fonctions OU logo téléversé (fichier privé, servi par la route contrôlée de la page), plage de grades
--    (niveaux OTAN 1 à 19, ceux de l'adéquation de l'organisation), ordre d'affichage, archivage (masqué sans être supprimé) ;
--  - access_role_tag_links : étiquettes du catalogue partagé avec les fonctions (personnel_job_role_tags) ;
--  - access_role_qualifications : qualifications du référentiel requises, recommandées ou délivrées par le niveau ;
--  - access_role_jobs : emplois associés au niveau (personnel_job_roles).
-- Unicités dans les CREATE TABLE (compatible SQLite). Pas de clé étrangère : la suppression d'un niveau nettoie ses lignes.
-- Exécuté par database/steps/4870_access_role_profiles.php. Retour arrière : migrations/rollback/access_role_profiles_down.sql.

CREATE TABLE IF NOT EXISTS access_role_profiles (
  role_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  abbreviation VARCHAR(8) NULL COMMENT '2 à 8 majuscules ou chiffres, unique dans la communauté',
  details TEXT NULL COMMENT 'description détaillée, texte riche simple (App\\Support\\RichTextLite)',
  icon VARCHAR(40) NULL COMMENT 'clé de App\\Support\\JobRoleIconLibrary',
  logo_path VARCHAR(190) NULL COMMENT 'fichier privé storage/private/access-role-logos',
  logo_mime VARCHAR(40) NULL,
  logo_version INT UNSIGNED NOT NULL DEFAULT 0,
  grade_min_level TINYINT UNSIGNED NULL COMMENT 'niveau OTAN (App\\Services\\Organization\\GradeLevel)',
  grade_max_level TINYINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  archived_at DATETIME NULL,
  archived_by INT UNSIGNED NULL,
  updated_at DATETIME NULL,
  updated_by INT UNSIGNED NULL,
  PRIMARY KEY (role_id),
  UNIQUE KEY uk_arp_tenant_abbreviation (tenant_id, abbreviation),
  KEY idx_arp_tenant (tenant_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_role_tag_links (
  tenant_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL COMMENT 'personnel_job_role_tags.id (catalogue partagé avec les fonctions)',
  PRIMARY KEY (role_id, tag_id),
  KEY idx_artl_tenant_tag (tenant_id, tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_role_qualifications (
  tenant_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  qualification_id INT UNSIGNED NOT NULL COMMENT 'personnel_qualification_definitions.id',
  kind VARCHAR(12) NOT NULL COMMENT 'required, recommended, granted',
  PRIMARY KEY (role_id, qualification_id),
  KEY idx_arq_tenant_qualification (tenant_id, qualification_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_role_jobs (
  tenant_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  job_role_id INT UNSIGNED NOT NULL COMMENT 'personnel_job_roles.id',
  PRIMARY KEY (role_id, job_role_id),
  KEY idx_arj_tenant_job (tenant_id, job_role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
