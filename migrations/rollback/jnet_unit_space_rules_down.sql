-- Retour arrière de migrations/2026_11_14_060_jnet_unit_space_rules.sql (étape jnet_unit_space_rules). À lancer à la
-- main : efface les droits par unité, les gestionnaires, le journal des relais automatiques et les trois réglages.
DROP TABLE IF EXISTS jnet_unit_auto_relays;
DROP TABLE IF EXISTS jnet_unit_space_managers;
DROP TABLE IF EXISTS jnet_unit_space_access;
ALTER TABLE jnet_unit_spaces DROP COLUMN post_min, DROP COLUMN auto_relay, DROP COLUMN require_ack;
