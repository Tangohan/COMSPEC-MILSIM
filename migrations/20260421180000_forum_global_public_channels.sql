-- Forum : canaux « grands publics » en scope global (visibles pour tous les tenants).
-- Les racines dont le slug commence par org- restent l’espace dédié à la communauté (scope tenant).
-- À exécuter une fois après déploiement du correctif de mapping scope (Repository + API).

SET NAMES utf8mb4;

UPDATE forum_categories
SET scope = 'global', tenant_id = NULL, owner_tenant_id = NULL
WHERE parent_id IS NULL
  AND COALESCE(scope, 'tenant') IN ('tenant', 'general', 'platform')
  AND slug NOT LIKE 'org-%';

UPDATE forum_categories c
INNER JOIN forum_categories p ON p.id = c.parent_id
SET c.scope = 'global', c.tenant_id = NULL, c.owner_tenant_id = NULL
WHERE p.scope = 'global'
  AND p.tenant_id IS NULL
  AND c.parent_id IS NOT NULL;
