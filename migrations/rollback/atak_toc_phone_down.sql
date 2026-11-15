-- Retour arrière de l'étape atak_toc_phone : supprime le journal des actions sur les téléphones (perte de l'historique
-- des localisations et du journal d'audit propre à cette fonction ; le journal général audit_logs, lui, est conservé)
-- et le réglage de chaque communauté (la fonction redevient désactivée). La file atak_web_commands n'est pas touchée.
-- Les droits atak.toc.phone restent dans permissions ; les retirer à part si besoin :
--   DELETE rp FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE p.slug = 'atak.toc.phone';
--   DELETE FROM permissions WHERE slug = 'atak.toc.phone';
DROP TABLE IF EXISTS atak_toc_phone_events;
DROP TABLE IF EXISTS atak_toc_phone_settings;
