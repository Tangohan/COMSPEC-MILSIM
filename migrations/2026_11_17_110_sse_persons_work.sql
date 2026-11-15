-- SSE Personnes : classification, verrouillage et clôture des fiches, dates de dernière ouverture par utilisateur.
-- Colonnes de sse_persons (ajoutées par l'étape 4760 seulement si elles manquent) :
--   classification  code de l'échelle SSE (interne, encadrement, confidentiel, tres_restreint), NULL = non classifiée ;
--   locked_at, locked_by, lock_reason, lock_until  verrouillage manuel (date de fin facultative) ;
--   closed_at, closed_by, close_reason  clôture du dossier (code du motif ; jamais de suppression, réouverture possible).
-- Retour arrière : migrations/rollback/sse_persons_work_down.sql.
ALTER TABLE sse_persons ADD COLUMN classification VARCHAR(16) DEFAULT NULL;
ALTER TABLE sse_persons ADD COLUMN locked_at DATETIME DEFAULT NULL;
ALTER TABLE sse_persons ADD COLUMN locked_by INT UNSIGNED DEFAULT NULL;
ALTER TABLE sse_persons ADD COLUMN lock_reason VARCHAR(500) DEFAULT NULL;
ALTER TABLE sse_persons ADD COLUMN lock_until DATETIME DEFAULT NULL;
ALTER TABLE sse_persons ADD COLUMN closed_at DATETIME DEFAULT NULL;
ALTER TABLE sse_persons ADD COLUMN closed_by INT UNSIGNED DEFAULT NULL;
ALTER TABLE sse_persons ADD COLUMN close_reason VARCHAR(500) DEFAULT NULL;
