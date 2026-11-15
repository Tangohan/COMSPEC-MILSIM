-- Qualifications d'un membre : choix d'affichage par qualification (visible en jeu sur la fiche opérateur de l'ATAK, visible sur le
-- profil vu par les autres membres et sur le C.V. public). UNE table NOUVELLE, une ligne par attribution (personnel_qualifications.id) :
-- aucune colonne ajoutée ni donnée modifiée. Une attribution sans ligne ici reste visible aux deux endroits (comportement d'avant).
-- Le masquage ne touche que l'affichage : la qualification reste détenue et compte toujours pour les prérequis, les accès et les kits.
-- L'unicité est dans le CREATE TABLE (clé primaire). Sans cette table, tout reste visible et les interrupteurs sont désactivés.
-- Retour arrière : migrations/rollback/qualification_display_prefs_down.sql.

CREATE TABLE IF NOT EXISTS personnel_qualification_display (
  qualification_id INT UNSIGNED NOT NULL COMMENT 'personnel_qualifications.id',
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL COMMENT 'membre titulaire',
  show_in_game TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 : absente de la fiche opérateur envoyée au jeu',
  show_on_profile TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 : absente du profil vu par les autres et du C.V. public',
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (qualification_id),
  KEY idx_pq_display_member (tenant_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
