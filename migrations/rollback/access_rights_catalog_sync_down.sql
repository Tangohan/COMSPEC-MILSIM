-- Retour arrière de l'étape access_rights_catalog_sync (droits training.publications.manage et events.manage).
-- Retire d'abord les liens avec les niveaux, puis les droits eux-mêmes. À n'exécuter que si plus aucun niveau ne les utilise.
DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE slug IN ('training.publications.manage', 'events.manage'));
DELETE FROM permissions WHERE slug IN ('training.publications.manage', 'events.manage');
