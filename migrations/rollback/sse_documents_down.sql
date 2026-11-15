-- Retour arrière de migrations/2026_11_16_100_sse_documents.sql (étape sse_documents). À lancer à la main : efface les
-- documents émis (texte imprimé compris), les brouillons et les trames de phrases. Les fiches, identités, avis de recherche
-- et la chronologie des identités ne sont pas touchés.
DROP TABLE IF EXISTS sse_doc_phrases;
DROP TABLE IF EXISTS sse_doc_drafts;
DROP TABLE IF EXISTS sse_doc_issues;
