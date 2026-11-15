-- Retour arrière de l'étape sse_interest_case_suite (database/steps/4710_sse_interest_case_suite.php). À lancer à la main :
-- efface l'historique champ par champ, les éléments liés et les abonnés des dossiers d'intérêt, puis retire les colonnes
-- ajoutées à sse_interest_cases. Les dossiers eux-mêmes, leur journal de mises à jour et leurs destinataires ne sont pas touchés.
DROP TABLE IF EXISTS sse_interest_case_watchers;
DROP TABLE IF EXISTS sse_interest_case_links;
DROP TABLE IF EXISTS sse_interest_case_history;
ALTER TABLE sse_interest_cases
  DROP INDEX idx_sse_interest_trash,
  DROP COLUMN version,
  DROP COLUMN classification,
  DROP COLUMN case_type,
  DROP COLUMN assignee_user_id,
  DROP COLUMN due_at,
  DROP COLUMN tags,
  DROP COLUMN archive_from_status,
  DROP COLUMN deleted_at,
  DROP COLUMN deleted_by,
  DROP COLUMN delete_reason;
