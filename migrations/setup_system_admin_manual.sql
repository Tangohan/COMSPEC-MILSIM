-- =============================================================================
-- Super-admin plateforme (site_super_admin) + propriétaire sur tenant « default »
--
-- Cas couverts :
--   - Aucun tenant : crée le tenant slug `default`, rôles community_owner / tenant_admin.
--   - Grades : schéma actuel = référentiel global (`grades` sans tenant_id). On prend un grade déjà en base.
--   - Pas encore de rôle site : crée permissions site + rôle site_super_admin + liaisons.
--   - Compte inexistant : INSERT utilisateur lié au tenant.
--
-- À personnaliser : @email et @hash uniquement.
-- =============================================================================

SET @email = LOWER('tetard.tanguy@gmail.com');
SET @hash = '$argon2id$v=19$m=65536,t=4,p=1$REMPLACER_LE_HASH_ARGON2';

-- --- 1) Tenant « default » (obligatoire : chaque user a un tenant_id) ---
INSERT INTO tenants (name, slug, created_at, updated_at)
SELECT 'Pas d''organisation', 'default', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tenants WHERE slug = 'default' LIMIT 1);

SET @tid = (SELECT id FROM tenants WHERE slug = 'default' LIMIT 1);

-- --- 2) Rôles communauté sur ce tenant ---
INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at)
SELECT @tid, 'Propriétaire communauté', 'community_owner', 'Gouvernance complète', 1, 1, 'community', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE tenant_id = @tid AND slug = 'community_owner' LIMIT 1);

INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at)
SELECT @tid, 'Administrator', 'tenant_admin', 'Administration organisation', 1, 0, 'community', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE tenant_id = @tid AND slug = 'tenant_admin' LIMIT 1);

SET @role_id = COALESCE(
  (SELECT id FROM roles WHERE tenant_id = @tid AND slug = 'community_owner' LIMIT 1),
  (SELECT id FROM roles WHERE tenant_id = @tid AND slug = 'tenant_admin' LIMIT 1)
);

-- --- 3) Grade utilisateur (référentiel global : la table `grades` n’a plus de tenant_id) ---
-- Prend le premier grade actif ; sinon n’importe quel id (base vide => grade_id NULL).
SET @grade_id = COALESCE(
  (SELECT id FROM grades WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1),
  (SELECT id FROM grades ORDER BY id ASC LIMIT 1)
);

-- --- 4) RBAC site minimal (si migrations pas encore passées) ---
INSERT INTO permissions (tenant_id, name, slug, module, scope, created_at)
SELECT NULL, 'Administration système (plateforme)', 'admin.system', 'admin', 'site', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE tenant_id IS NULL AND slug = 'admin.system' LIMIT 1);

INSERT INTO permissions (tenant_id, name, slug, module, scope, created_at)
SELECT NULL, 'Accès back-office plateforme', 'admin.access', 'admin', 'site', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE tenant_id IS NULL AND slug = 'admin.access' LIMIT 1);

INSERT INTO permissions (tenant_id, name, slug, module, scope, created_at)
SELECT NULL, 'Gérer les communautés (tenants)', 'site.tenants.manage', 'admin', 'site', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE tenant_id IS NULL AND slug = 'site.tenants.manage' LIMIT 1);

INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at)
SELECT NULL, 'Super administrateur site', 'site_super_admin', 'Administration plateforme (global)', 1, 1, 'site', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE tenant_id IS NULL AND slug = 'site_super_admin' LIMIT 1);

SET @site_rid = (SELECT id FROM roles WHERE tenant_id IS NULL AND slug = 'site_super_admin' LIMIT 1);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT @site_rid, id FROM permissions WHERE tenant_id IS NULL AND slug IN ('admin.system', 'admin.access', 'site.tenants.manage');

-- --- 5) Compte seed admin@athena.local -> nouvel email + mot de passe ---
UPDATE users SET
  email = @email,
  password_hash = @hash,
  role_id = @role_id,
  grade_id = IF(IFNULL(@grade_id, 0) > 0, @grade_id, grade_id),
  status = 'active',
  updated_at = NOW()
WHERE tenant_id = @tid AND email = 'admin@athena.local';

UPDATE site_role_assignments
SET email_normalized = @email
WHERE email_normalized = 'admin@athena.local' AND role_id = @site_rid;

-- --- 6) Compte déjà présent avec cet email ---
UPDATE users SET
  password_hash = @hash,
  role_id = @role_id,
  grade_id = IF(IFNULL(@grade_id, 0) > 0, @grade_id, grade_id),
  status = 'active',
  updated_at = NOW()
WHERE tenant_id = @tid AND email = @email;

-- --- 7) Création utilisateur si toujours absent ---
INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, role_id, grade_id, status, created_at, updated_at)
SELECT @tid, @email, @hash, 'Administrateur', 'ADMIN', @role_id,
  IF(IFNULL(@grade_id, 0) > 0, @grade_id, NULL),
  'active', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM users WHERE tenant_id = @tid AND email = @email LIMIT 1
);

INSERT INTO site_role_assignments (email_normalized, role_id, created_at)
VALUES (@email, @site_rid, NOW())
ON DUPLICATE KEY UPDATE revoked_at = NULL;

-- Vérification
SELECT @tid AS tenant_id, @role_id AS role_communaute, @site_rid AS role_site;
SELECT id, tenant_id, email, status, role_id, grade_id FROM users WHERE tenant_id = @tid AND email = @email;
