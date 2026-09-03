-- Modèle simple : Opérateur obligatoire, habilitations fonctionnelles ciblées,
-- positions administratives séparées et ancien catalogue rangé en Roleplay.

UPDATE roles SET name = 'Opérateur', is_locked = 1, category = 'Accès', subcategory = 'Socle'
WHERE tenant_id IS NOT NULL AND slug = 'member';
UPDATE roles SET name = 'Recrutement', category = 'Habilitations', subcategory = 'Recrutement'
WHERE tenant_id IS NOT NULL AND slug = 'recruiter';
UPDATE roles SET name = 'Ressources humaines', category = 'Habilitations', subcategory = 'Effectifs'
WHERE tenant_id IS NOT NULL AND slug = 'hr';
UPDATE roles SET name = 'Gestionnaire', category = 'Habilitations', subcategory = 'Gestion'
WHERE tenant_id IS NOT NULL AND slug = 'community_owner';
UPDATE roles SET name = 'Gestionnaire adjoint', category = 'Habilitations', subcategory = 'Gestion'
WHERE tenant_id IS NOT NULL AND slug IN ('tenant_admin', 'deputy_commander');
UPDATE roles SET name = 'Formateur', category = 'Habilitations', subcategory = 'Formation'
WHERE tenant_id IS NOT NULL AND slug = 'trainer';
UPDATE roles SET name = 'Responsable des formateurs', category = 'Habilitations', subcategory = 'Formation'
WHERE tenant_id IS NOT NULL AND slug = 'senior_instructor';
UPDATE roles SET name = 'Instructeur', category = 'Habilitations', subcategory = 'Formation'
WHERE tenant_id IS NOT NULL AND slug = 'instructor';
UPDATE roles SET category = 'Positions administratives', subcategory = 'Enrôlement', is_visual_only = 1
WHERE tenant_id IS NOT NULL AND slug IN ('status_in_training', 'status_active_duty');

UPDATE roles SET category = 'Rôles roleplay', subcategory = COALESCE(NULLIF(subcategory, ''), 'Catalogue historique')
WHERE tenant_id IS NOT NULL
  AND slug NOT IN ('member','recruiter','hr','community_owner','tenant_admin','deputy_commander',
                   'trainer','senior_instructor','instructor','status_in_training','status_active_duty');

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u INNER JOIN roles r ON r.tenant_id = u.tenant_id AND r.slug = 'member'
WHERE u.status = 'active' AND u.email NOT LIKE 'system-moderator+%@athena.internal';

INSERT IGNORE INTO tenant_user_roles (tenant_id, user_id, role_id, org_unit_id, co_unit_id, created_at)
SELECT u.tenant_id, u.id, r.id, NULL, 0, NOW()
FROM users u INNER JOIN roles r ON r.tenant_id = u.tenant_id AND r.slug = 'member'
WHERE u.status = 'active' AND u.email NOT LIKE 'system-moderator+%@athena.internal';

-- Les pages restent contrôlées par permissions : ces profils ne reçoivent que leur périmètre.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r INNER JOIN permissions p ON p.tenant_id = r.tenant_id
WHERE r.slug = 'recruiter' AND p.slug IN ('organization.recruitment','organization.recruitment.manage','organization.recruitment.openings.manage');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r INNER JOIN permissions p ON p.tenant_id = r.tenant_id
WHERE r.slug = 'hr' AND p.slug IN ('organization.effectifs.hub.view','organization.recruitment','organization.recruitment.manage','organization.recruitment.openings.manage');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r INNER JOIN permissions p ON p.tenant_id = r.tenant_id
WHERE r.slug IN ('trainer','instructor') AND p.slug IN ('training.view','training.create','training.update','training.manage','training.assign','training.submissions.grade','training.results.view');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r INNER JOIN permissions p ON p.tenant_id = r.tenant_id
WHERE r.slug = 'senior_instructor' AND p.slug IN ('training.view','training.create','training.update','training.delete','training.publish','training.manage','training.assign','training.submissions.grade','training.results.view','training.certifications.manage','training.publications.manage');
