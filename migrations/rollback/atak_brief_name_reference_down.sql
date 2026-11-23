-- Retour arrière de l'étape atak_brief_name_reference : retire le nom et la référence des briefs air (9-LINE, 5-LINE, MEDEVAC).
-- Les lignes, destinataires et accusés des briefs ne sont pas touchés. Le registre des références est supprimé avec elles :
-- relancer l'étape plus tard repart d'un registre vide (des références déjà dites à la radio pourraient alors resservir).
DROP INDEX idx_nine_line_brief_ref ON atak_nine_line;
ALTER TABLE atak_nine_line DROP COLUMN brief_name, DROP COLUMN brief_ref;
DROP TABLE IF EXISTS atak_brief_refs;
