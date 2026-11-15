-- Retour arrière de l'étape atak_toc_purge_permission : retire le droit atak.toc.purge et ses attributions.
DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE slug = 'atak.toc.purge');
DELETE FROM permissions WHERE slug = 'atak.toc.purge';
