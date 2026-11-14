-- Retour arrière de migrations/2026_11_14_050_jnet_unit_workspace.sql (étape jnet_unit_workspace). À lancer à la main :
-- efface les tâches, pages, réactions, commentaires et votes des espaces d'unité, et la colonne hidden_modules.
DROP TABLE IF EXISTS jnet_unit_page_votes;
DROP TABLE IF EXISTS jnet_unit_page_comments;
DROP TABLE IF EXISTS jnet_unit_page_reactions;
DROP TABLE IF EXISTS jnet_unit_pages;
DROP TABLE IF EXISTS jnet_unit_tasks;
ALTER TABLE jnet_unit_spaces DROP COLUMN hidden_modules;
