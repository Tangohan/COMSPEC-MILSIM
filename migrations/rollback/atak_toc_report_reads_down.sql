-- Retour arrière de migrations/2026_11_16_200_atak_toc_report_reads.sql (étape atak_toc_report_reads). À lancer à la main :
-- efface les marques « lu » des opérateurs du poste TOC web (tout redevient « non lu »). Les rapports ne sont pas touchés.
DROP TABLE IF EXISTS atak_toc_report_reads;
