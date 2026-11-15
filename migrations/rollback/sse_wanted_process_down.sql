-- Retour arrière de migrations/2026_11_18_100_sse_wanted_process.sql (étape sse_wanted_process). À lancer à la main : efface le
-- service, la classification, les renouvellements, l'état de validation et l'historique des prolongations de chaque avis. Les avis,
-- la chronologie et les réglages (dont les services de la communauté) ne sont pas touchés.
DROP TABLE IF EXISTS sse_wanted_notice_meta;
