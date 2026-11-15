-- Retour arrière de l'étape member_dossier_steps : retire les étapes personnalisées, les compléments et l'historique du
-- suivi de dossier, puis les droits effectifs.suivi.view et effectifs.suivi.manage. Les parcours d'origine ne sont pas touchés.
DROP TABLE IF EXISTS member_dossier_step_events;
DROP TABLE IF EXISTS member_dossier_steps;
DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE slug IN ('effectifs.suivi.view', 'effectifs.suivi.manage'));
DELETE FROM permissions WHERE slug IN ('effectifs.suivi.view', 'effectifs.suivi.manage');
