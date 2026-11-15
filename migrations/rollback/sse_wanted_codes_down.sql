-- Retour arrière de migrations/2026_11_16_130_sse_wanted_codes.sql (étape sse_wanted_codes). À lancer à la main : efface les codes,
-- les signalements physiques et les lignes des pièces jointes des avis. Les fichiers image restent sur le disque, dans
-- storage/private/sse-images/sse-wanted/ (à supprimer à la main si besoin). Les avis, la chronologie et les réglages ne sont pas touchés.
DROP TABLE IF EXISTS sse_wanted_attachments;
DROP TABLE IF EXISTS sse_wanted_notice_extras;
