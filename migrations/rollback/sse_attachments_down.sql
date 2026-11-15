-- Retour arrière de l'étape sse_attachments (fenêtre « Rattacher » du bureau SSE).
-- Les liens eux-mêmes (sse_entity_links) et les colonnes historiques (case_id, sse_case_persons...) ne sont pas touchés :
-- seuls l'enrichissement des rattachements (libellé, étiquettes, note, alignement), le catalogue des étiquettes et les pistes
-- sont perdus. Les liens vers une piste restent dans sse_entity_links et ne s'affichent plus.
DROP TABLE IF EXISTS sse_link_attachments;
DROP TABLE IF EXISTS sse_attach_tags;
DROP TABLE IF EXISTS sse_leads;
