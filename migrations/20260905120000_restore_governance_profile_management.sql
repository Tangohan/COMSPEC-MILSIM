-- Répare les communautés créées avant le profil simplifié : un Gestionnaire
-- (chef de corps) doit toujours pouvoir administrer l'intégralité des fiches.
-- admin.organization implique les droits personnel.*, sans ouvrir admin.system.

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p
    ON p.tenant_id = r.tenant_id
   AND p.slug = 'admin.organization'
WHERE r.tenant_id IS NOT NULL
  AND r.slug IN ('community_owner', 'tenant_admin');
