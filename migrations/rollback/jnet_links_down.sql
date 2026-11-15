-- Retour arrière de migrations/2026_11_14_070_jnet_links.sql (étape jnet_links). À lancer à la main : efface les
-- liaisons entre unités et les liens entre contenus. Les unités, échanges, pages, tâches et fichiers ne sont pas touchés.
DROP TABLE IF EXISTS jnet_links;
DROP TABLE IF EXISTS jnet_unit_links;
