-- Étend le socle du rôle système « Opérateur » aux fonctions quotidiennes en lecture
-- et aux contributions sans privilège d'administration.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p
    ON p.tenant_id = r.tenant_id
   AND p.slug IN (
       'personnel.profile.view',
       'personnel.progression.view',
       'operations.sitrep.view',
       'operations.sitrep.create',
       'operations.aar.view',
       'operations.readiness.view',
       'operations.medical.view',
       'operations.logistics.view',
       'operations.comms.view',
       'operations.doctrine.view',
       'doctrine.view',
       'media.view',
       'intel.transmission.view',
       'intel.transmission.contribute',
       'cooperation.missions.view',
       'cooperation.exchange.read',
       'cooperation.exchange.write',
       'cooperation.rex.submit',
       'cooperation.rex.read'
   )
WHERE r.slug = 'member'
  AND r.is_system = 1;
