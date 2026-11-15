-- Retour arrière de migrations/2026_11_15_090_jnet_pins.sql (étape jnet_pins). À lancer à la main : efface les éléments
-- épinglés de l'espace commun. Les échanges, cibles, unités, pages et renseignements ne sont pas touchés.
DROP TABLE IF EXISTS jnet_pins;
