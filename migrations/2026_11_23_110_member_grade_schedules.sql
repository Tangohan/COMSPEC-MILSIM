-- Changements de grade planifiés (fiche membre du bureau des effectifs, tableau de bord de la communauté).
-- UNE table NOUVELLE, aucune colonne ajoutée ni donnée modifiée ailleurs. Une ligne par changement planifié :
--   status      pending (à venir), running (en cours d'application), applied (appliqué), cancelled (annulé : par une personne
--               habilitée, ou par la tâche automatique si le grade n'existe plus ou si le membre a quitté la communauté)
--   open_slot   vaut 1 tant que la ligne est « pending », NULL ensuite : l'unicité (communauté, membre, open_slot) interdit
--               deux changements à venir pour le même membre sans empêcher l'historique
--   status_reason  motif d'annulation ou remarque d'application
-- L'application réelle (users.grade_id, historique member_grade_changes avec l'origine « planifié ») est faite par la tâche
-- automatique member_grade_schedule ; elle revendique chaque ligne par un UPDATE conditionnel, donc sans doublon.
-- Sans cette table : le changement de grade immédiat fonctionne comme avant, la planification n'est simplement pas proposée.
-- Retour arrière : migrations/rollback/member_grade_schedules_down.sql.

CREATE TABLE IF NOT EXISTS member_grade_schedules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  from_grade_id INT UNSIGNED NULL COMMENT 'grade du membre au moment de la planification (informatif)',
  to_grade_id INT UNSIGNED NOT NULL,
  to_label VARCHAR(150) NULL COMMENT 'libellé copié à la planification, lisible si le grade disparaît',
  scheduled_for DATETIME NOT NULL,
  reason VARCHAR(400) NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending' COMMENT 'pending, running, applied, cancelled',
  open_slot TINYINT(1) NULL DEFAULT 1 COMMENT '1 tant que pending, NULL ensuite',
  status_reason VARCHAR(400) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  finished_by INT UNSIGNED NULL COMMENT 'personne qui a annulé, NULL si la tâche automatique',
  PRIMARY KEY (id),
  UNIQUE KEY uq_mgs_open (tenant_id, user_id, open_slot),
  KEY idx_mgs_due (status, scheduled_for),
  KEY idx_mgs_tenant (tenant_id, status, scheduled_for)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
