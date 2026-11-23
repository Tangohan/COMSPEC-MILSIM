-- Retour arrière de l'étape 5070 : supprime les tables ajoutées et la colonne sheet_theme (les références déjà attribuées restent dans sse_documents).
DROP TABLE IF EXISTS sse_document_signatures;
DROP TABLE IF EXISTS sse_document_counters;
ALTER TABLE sse_documents DROP COLUMN sheet_theme;
