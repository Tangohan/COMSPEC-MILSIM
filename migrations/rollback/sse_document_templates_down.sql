-- Retour arrière de migrations/2026_11_25_100_sse_document_templates.sql (étape sse_document_templates) : efface les modèles de documents personnels et partagés du bureau SSE ; les documents ne sont pas touchés.
DROP TABLE IF EXISTS sse_document_templates;
