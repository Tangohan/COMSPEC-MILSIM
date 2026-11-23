-- Retour arrière de migrations/2026_11_24_100_sse_document_drafts.sql (étape sse_document_drafts) : efface les brouillons automatiques de l'éditeur de documents SSE ; les documents ne sont pas touchés.
DROP TABLE IF EXISTS sse_document_drafts;
