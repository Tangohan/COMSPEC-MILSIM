-- Portail candidat : affichage du statut (étapes vs libellé manuel) + bande couleur associée.
ALTER TABLE enlistments
ADD COLUMN candidate_portal_status_mode VARCHAR(16) NOT NULL DEFAULT 'steps' COMMENT 'steps|manual',
ADD COLUMN candidate_portal_status_manual_text VARCHAR(280) NULL DEFAULT NULL,
ADD COLUMN candidate_portal_status_manual_band VARCHAR(16) NOT NULL DEFAULT 'amber';
