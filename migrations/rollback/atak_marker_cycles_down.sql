-- Retour arrière de l'étape atak_marker_cycles : supprime les missions archivées (perte des archives) et les réglages.
-- À n'exécuter qu'après avoir exporté les archives utiles (/api/atak/cycle/archives/{id}/export.json) ; la tâche
-- « atak_marker_cycle » doit être désactivée avant (sinon elle retire de la carte sans pouvoir archiver : elle s'arrête d'elle-même).
DROP TABLE IF EXISTS atak_marker_archive_items;
DROP TABLE IF EXISTS atak_marker_archives;
DROP TABLE IF EXISTS atak_marker_cycle_settings;
