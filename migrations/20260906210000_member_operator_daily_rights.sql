-- Pack Membre / Opérateur : vie courante, ATAK personnel, back-office personnel.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p
    ON p.tenant_id = r.tenant_id
   AND p.slug IN (
       'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
       'documents.view', 'documents.download.standard',
       'training.view',
       'personnel.profile.view', 'personnel.progression.view',
       'operational.board.view',
       'organization.orbat.view',
       'operations.tactical.view',
       'operations.missions.view',
       'operations.sitrep.view', 'operations.sitrep.create',
       'operations.aar.view', 'operations.readiness.view',
       'operations.medical.view', 'operations.logistics.view',
       'operations.comms.view', 'operations.doctrine.view',
       'doctrine.view', 'media.view',
       'intel.transmission.view', 'intel.transmission.contribute',
       'cooperation.missions.view',
       'cooperation.exchange.read', 'cooperation.exchange.write',
       'cooperation.rex.submit', 'cooperation.rex.read',
       'admin.backoffice.view',
       'atak.terminals.view'
   )
WHERE r.tenant_id IS NOT NULL
  AND r.slug IN ('member', 'hr');

UPDATE role_module_access rma
INNER JOIN roles r ON r.id = rma.role_id AND r.tenant_id = rma.tenant_id
SET rma.access_level = 'sa_fiche', rma.updated_at = NOW()
WHERE r.slug = 'member'
  AND rma.module_key IN ('atak', 'systems')
  AND rma.access_level IN ('none', '');
