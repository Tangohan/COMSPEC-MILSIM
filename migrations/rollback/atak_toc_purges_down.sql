-- Retour arrière de l'étape atak_toc_soft_delete : supprime le journal des purges et les colonnes de suppression douce.
-- Avant d'exécuter : les éléments encore dans la corbeille (deleted_at non nul) sont supprimés pour de bon, sinon les
-- tracés perdraient leur masque et réapparaîtraient sur la carte (les marqueurs restent masqués par leur état « suppressed »).
-- Laisser se terminer ou annuler les purges en cours depuis l'écran « Purger » avant.
DELETE FROM atak_map_shapes WHERE deleted_at IS NOT NULL;
DELETE FROM atak_markers WHERE deleted_at IS NOT NULL AND (arma_name IS NULL OR arma_name = '');
DROP TABLE IF EXISTS atak_toc_purges;
ALTER TABLE atak_markers DROP INDEX idx_atak_markers_trash;
ALTER TABLE atak_markers DROP COLUMN deleted_at, DROP COLUMN deleted_by, DROP COLUMN delete_batch;
ALTER TABLE atak_map_shapes DROP INDEX idx_atak_map_shapes_trash;
ALTER TABLE atak_map_shapes DROP COLUMN deleted_at, DROP COLUMN deleted_by, DROP COLUMN delete_batch;
