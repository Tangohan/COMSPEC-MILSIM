-- Socle Membre / Opérateur : consultation personnelle du back-office et des terminaux ATAK.
-- N’ouvre pas l’administration (utilisateurs, paramètres, tableur des liaisons).
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p
    ON p.tenant_id = r.tenant_id
   AND p.slug IN (
       'admin.backoffice.view',
       'atak.terminals.view'
   )
WHERE r.tenant_id IS NOT NULL
  AND r.slug IN ('member', 'hr');
