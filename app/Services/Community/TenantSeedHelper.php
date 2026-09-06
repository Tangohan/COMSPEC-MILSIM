<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Authorization\TenantPermissionCatalog;
use App\Repositories\UnitRepository;
use App\Services\Rbac\CommunityAccessProfiles;
use App\Services\Training\TenantPedagogyStructureService;
use App\Support\SqlText;
use PDO;
use PDOException;

/**
 * Reprise des seeds run-migrations.php pour un nouveau tenant (forum, documents, permissions admin de base).
 */
final class TenantSeedHelper
{
    public static function seedForumAndRoles(PDO $pdo, int $tenantId): void
    {
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $stmt->execute([$tenantId, 'forum.view']);
        $hasForumView = (bool) $stmt->fetch();

        if (!$hasForumView) {
            $permissions = [
                ['admin.access', 'Accès administration', 'admin'],
                ['forum.view', 'Voir le forum', 'forum'],
                ['forum.create_topic', 'Créer un sujet', 'forum'],
                ['forum.reply', 'Répondre', 'forum'],
                ['forum.edit_own', 'Modifier son message', 'forum'],
                ['forum.delete_own', 'Supprimer son message', 'forum'],
                ['forum.moderate', 'Modérer le forum', 'forum'],
                ['forum.moderate_organization', 'Modérer la section forum de l\'organisation', 'forum'],
                ['forum.manage_categories', 'Gérer les catégories', 'forum'],
            ];

            $permIds = [];
            foreach ($permissions as $p) {
                $permIds[$p[0]] = self::insertPermission($pdo, $tenantId, $p[1], $p[0], $p[2]);
            }

            self::ensureAccessProfilesForTenant($pdo, $tenantId);
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach (CommunityAccessProfiles::slugs() as $roleSlug) {
                $rs = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
                $rs->execute([$tenantId, $roleSlug]);
                $roleId = (int) ($rs->fetchColumn() ?: 0);
                if ($roleId < 1) {
                    continue;
                }
                foreach ($permIds as $pid) {
                    $link->execute([$roleId, $pid]);
                }
            }
        } else {
            // Permissions déjà présentes : rattacher le minimum aux rôles simplifiés + gouvernance.
            $forumSlugs = ['forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own'];
            $permIds = [];
            foreach ($forumSlugs as $slug) {
                $s = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
                $s->execute([$tenantId, $slug]);
                $id = (int) ($s->fetchColumn() ?: 0);
                if ($id > 0) {
                    $permIds[$slug] = $id;
                }
            }
            if ($permIds !== []) {
                foreach (CommunityAccessProfiles::slugs() as $roleSlug) {
                    $rs = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
                    $rs->execute([$tenantId, $roleSlug]);
                    $roleId = (int) ($rs->fetchColumn() ?: 0);
                    if ($roleId < 1) {
                        continue;
                    }
                    $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
                    foreach ($permIds as $pid) {
                        $link->execute([$roleId, $pid]);
                    }
                }
            }
        }

        $stmt = $pdo->prepare('SELECT 1 FROM forum_categories WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        if ($stmt->fetch()) {
            self::ensurePedagogyMandatoryUnits($tenantId);

            return;
        }

        $categories = [
            ['Communiqués officiels', 'annonces', 'Annonces et communiqués de l\'équipe.', 'orange', 10],
            ['Général', 'general', 'Discussions générales et présentation.', 'indigo', 20],
            ['Missions & Opérations', 'missions', 'Briefs et retours d\'opérations.', 'violet', 30],
            ['Support & Technique', 'support', 'Aide, ATAK, équipement, technique.', 'rose', 40],
            ['Hors sujet', 'hors-sujet', 'Échanges informels.', 'emerald', 50],
        ];
        $insCat = $pdo->prepare('INSERT INTO forum_categories (tenant_id, parent_id, name, slug, description, color_theme, display_order, is_locked, created_at, updated_at) VALUES (?, NULL, ?, ?, ?, ?, ?, 0, NOW(), NOW())');
        foreach ($categories as $c) {
            $insCat->execute([$tenantId, $c[0], $c[1], $c[2], $c[3], $c[4]]);
        }

        self::ensurePedagogyMandatoryUnits($tenantId);
    }

    private static function ensurePedagogyMandatoryUnits(int $tenantId): void
    {
        if ($tenantId < 1) {
            return;
        }
        try {
            (new TenantPedagogyStructureService(new UnitRepository()))->ensureMandatorySectionsForTenant($tenantId);
        } catch (\Throwable $_) {
        }
    }

    /**
     * Catégorie forum « organisation » (section dédiée) — idempotent.
     */
    public static function ensureOrganizationForumSection(PDO $pdo, int $tenantId): void
    {
        $stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_categories' AND COLUMN_NAME = 'scope' LIMIT 1");
        if (!$stmt || !$stmt->fetchColumn()) {
            return;
        }
        $st = $pdo->prepare('SELECT name, slug FROM tenants WHERE id = ? LIMIT 1');
        $st->execute([$tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || ($row['slug'] ?? '') === 'default') {
            // Tenant système « pas d’organisation » : jamais de section forum dédiée (ce n’est pas une vraie communauté).
            return;
        }
        $slug = 'org-' . preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) ($row['slug'] ?? '')));
        $slug = trim($slug, '-');
        if ($slug === 'org') {
            $slug = 'org-' . $tenantId;
        }
        if (strlen($slug) > 100) {
            $slug = substr('org-' . $tenantId . '-' . md5((string) ($row['slug'] ?? '')), 0, 100);
            $slug = rtrim($slug, '-');
        }
        $chkSlug = $pdo->prepare('SELECT 1 FROM forum_categories WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $chkSlug->execute([$tenantId, $slug]);
        if ($chkSlug->fetch()) {
            return;
        }
        $chk = $pdo->prepare("SELECT 1 FROM forum_categories WHERE tenant_id = ? AND scope = 'organization' LIMIT 1");
        $chk->execute([$tenantId]);
        if ($chk->fetch()) {
            return;
        }
        $name = trim((string) $row['name']) . ' — Espace dédié';
        if (strlen($name) > 255) {
            $name = substr($name, 0, 252) . '…';
        }
        $ins = $pdo->prepare('INSERT INTO forum_categories (tenant_id, scope, owner_tenant_id, parent_id, name, slug, description, color_theme, display_order, is_locked, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, 0, NOW(), NOW())');
        try {
            $ins->execute([$tenantId, 'organization', $tenantId, $name, $slug, 'Section forum de votre organisation.', 'slate', 15]);
        } catch (\PDOException $e) {
            // Idempotence / contraintes (slug, scope migré, etc.) — ne pas faire échouer le flux d’invitation.
        }
    }

    public static function seedDocumentsEquipment(PDO $pdo, int $tenantId): void
    {
        $docPermSlugs = ['documents.view', 'documents.upload', 'documents.update', 'documents.archive', 'documents.download_sensitive'];
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $stmt->execute([$tenantId, 'documents.view']);
        $docPermIds = [];
        if ($stmt->fetch()) {
            foreach ($docPermSlugs as $slug) {
                $s = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
                $s->execute([$tenantId, $slug]);
                $id = $s->fetch(PDO::FETCH_ASSOC);
                if ($id) {
                    $docPermIds[$slug] = (int) $id['id'];
                }
            }
        } else {
            $docPerms = [
                ['documents.view', 'Voir les documents', 'documents'],
                ['documents.upload', 'Uploader des documents', 'documents'],
                ['documents.update', 'Modifier les documents', 'documents'],
                ['documents.archive', 'Archiver les documents', 'documents'],
                ['documents.download_sensitive', 'Télécharger documents sensibles', 'documents'],
            ];
            foreach ($docPerms as $p) {
                $docPermIds[$p[0]] = self::insertPermission($pdo, $tenantId, $p[1], $p[0], $p[2]);
            }
        }
        $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach (CommunityAccessProfiles::slugs() as $roleSlug) {
            $rs = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
            $rs->execute([$tenantId, $roleSlug]);
            $roleId = (int) ($rs->fetchColumn() ?: 0);
            if ($roleId < 1 || $docPermIds === []) {
                continue;
            }
            if ($roleSlug === CommunityAccessProfiles::SLUG_MEMBER) {
                if (isset($docPermIds['documents.view'])) {
                    $link->execute([$roleId, $docPermIds['documents.view']]);
                }
                continue;
            }
            foreach ($docPermIds as $pid) {
                $link->execute([$roleId, $pid]);
            }
        }

        $stmt = $pdo->prepare('SELECT 1 FROM document_categories WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        if (!$stmt->fetch()) {
            foreach ([['Doctrine / SOP', 'doctrine'], ['Manuel opérateur', 'manuel'], ['Fiche équipement', 'fiche-equipement'], ['Rapport mission', 'rapport'], ['Média pédagogique', 'media']] as $i => $c) {
                $pdo->prepare('INSERT INTO document_categories (tenant_id, name, slug, color, created_at) VALUES (?, ?, ?, ?, NOW())')->execute([$tenantId, $c[0], $c[1], ['emerald', 'blue', 'amber', 'slate', 'violet'][$i] ?? null]);
            }
        }
        $stmt = $pdo->prepare('SELECT 1 FROM equipment_classes WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        if (!$stmt->fetch()) {
            foreach ([['Radio', 'radio', 'radio'], ['Optique', 'optic', 'optic'], ['Armement', 'weapon', 'weapon'], ['Véhicule', 'vehicle', 'vehicle'], ['Drone', 'drone', 'drone'], ['Médical', 'medical', 'medical']] as $c) {
                $pdo->prepare('INSERT INTO equipment_classes (tenant_id, name, slug, category, description, created_at) VALUES (?, ?, ?, ?, NULL, NOW())')->execute([$tenantId, $c[0], $c[1], $c[2]]);
            }
        }
    }

    public static function ensureSystemAdminPermissions(PDO $pdo, int $tenantId): void
    {
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $stmt->execute([$tenantId, 'admin.organization']);
        if (!$stmt->fetch()) {
            self::insertPermission($pdo, $tenantId, 'Administration organisationnelle', 'admin.organization', 'admin');
        }

        $stmt = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $stmt->execute([$tenantId, 'community_owner']);
        if (!$stmt->fetch()) {
            $co = TenantDefaultRoleDefinitions::governanceRoles()[0];
            $pdo->prepare("INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, ?, ?, ?, 1, 1, 'community', NOW())")
                ->execute([$tenantId, $co['name'], $co['slug'], $co['description']]);
            $coId = (int) $pdo->lastInsertId();
            foreach (['admin.organization', 'admin.access'] as $permSlug) {
                $p = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
                $p->execute([$tenantId, $permSlug]);
                $permId = $p->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
                if ($permId) {
                    $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$coId, $permId]);
                }
            }
        }

        $permOrg = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $permOrg->execute([$tenantId, 'admin.organization']);
        $permOrgId = $permOrg->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        $coR = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $coR->execute([$tenantId, 'community_owner']);
        $coRid = $coR->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        if ($coRid && $permOrgId) {
            $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([(int) $coRid, $permOrgId]);
        }

        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $stmt->execute([$tenantId, 'training.view']);
        if (!$stmt->fetch()) {
            foreach ([['training.view', 'Voir les formations', 'training'], ['training.manage', 'Gérer les formations', 'training'], ['training.assign', 'Assigner des formations', 'training'], ['training.publications.manage', 'Gérer les publications de formation', 'training']] as $p) {
                self::insertPermission($pdo, $tenantId, $p[1], $p[0], $p[2]);
            }
            foreach (['community_owner'] as $roleSlug) {
                $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
                $adminRole->execute([$tenantId, $roleSlug]);
                $adminRoleId = $adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
                if ($adminRoleId) {
                    $trainPerms = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . SqlText::inLiterals($pdo, 'slug', ['training.view', 'training.manage', 'training.assign', 'training.publications.manage']));
                    $trainPerms->execute([$tenantId]);
                    while ($row = $trainPerms->fetch(PDO::FETCH_ASSOC)) {
                        $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([(int) $adminRoleId, $row['id']]);
                    }
                }
            }
        }
    }

    /**
     * Libellés métier et rôles suggérés après seed forum / permissions (wizard onboarding).
     */
    public static function applyWizardCommunityRoles(PDO $pdo, int $tenantId, string $template): void
    {
        unset($template);
        self::ensureAccessProfilesForTenant($pdo, $tenantId);
    }

    /**
     * Rôles métier supplémentaires définis dans l’assistant (hors rôles système).
     *
     * @param list<array{name: string, slug: string, permission_slugs: list<string>}> $roles
     */
    public static function applyWizardCustomRoles(PDO $pdo, int $tenantId, array $roles): void
    {
        unset($pdo, $tenantId, $roles);
        // Les accès communauté sont uniquement Membre, Ressources humaines et Gestionnaire.
    }

    /**
     * Insère ou met à jour le catalogue de permissions (slug, module, action) pour un tenant
     * et rattache les rôles système (admin, propriétaire, modérateur forum).
     */
    public static function ensureTenantPermissionCatalog(PDO $pdo, int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $hasAction = self::permissionsTableHasActionColumn($pdo);
        $hasCode = self::permissionsTableHasCodeColumn($pdo);
        $hasLabel = self::permissionsTableHasLabelColumn($pdo);
        $defs = TenantPermissionCatalog::definitions();

        $selectId = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        foreach ($defs as $row) {
            $slug = $row['slug'];
            $selectId->execute([$tenantId, $slug]);
            $existing = $selectId->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $sets = ['name = ?', 'module = ?'];
                $params = [$row['name'], $row['module']];
                if ($hasAction) {
                    $sets[] = 'action = ?';
                    $params[] = $row['action'];
                }
                if ($hasLabel) {
                    $sets[] = 'label = ?';
                    $params[] = $row['name'];
                }
                if ($hasCode) {
                    $sets[] = "code = COALESCE(NULLIF(code, ''), ?)";
                    $params[] = $slug;
                }
                $params[] = $tenantId;
                $params[] = $slug;
                $pdo->prepare(
                    'UPDATE permissions SET ' . implode(', ', $sets) . ' WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1'
                )->execute($params);
                continue;
            }
            self::insertPermission(
                $pdo,
                $tenantId,
                $row['name'],
                $slug,
                $row['module'],
                $hasAction ? $row['action'] : null
            );
        }

        $permIdsBySlug = [];
        $q = $pdo->prepare('SELECT id, slug FROM permissions WHERE tenant_id = ?');
        $q->execute([$tenantId]);
        while ($pr = $q->fetch(PDO::FETCH_ASSOC)) {
            $permIdsBySlug[(string) $pr['slug']] = (int) $pr['id'];
        }

        self::ensureAccessProfilesForTenant($pdo, $tenantId);

        try {
            $stDef = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_definitions' LIMIT 1");
            if ($stDef && $stDef->fetchColumn()) {
                $pdo->prepare(
                    'UPDATE roles r
                     INNER JOIN role_definitions d
                       ON (d.slug COLLATE utf8mb4_unicode_ci) = (r.slug COLLATE utf8mb4_unicode_ci)
                     SET r.definition_id = d.id
                     WHERE r.tenant_id = ? AND r.definition_id IS NULL'
                )->execute([$tenantId]);
                \App\Services\Rbac\RoleDefinitionCatalog::seedTenantRoleRelations($pdo, $tenantId);
            }
        } catch (\Throwable) {
        }

        try {
            TenantDefaultRoleDefinitions::applyCanonicalLabels($pdo, $tenantId);
        } catch (\Throwable) {
        }
    }

    /**
     * Rattache les jeux de permissions métier (INSERT IGNORE) pour les rôles système connus.
     *
     * @param array<string, int> $permIdsBySlug
     */
    private static function applyDefaultRolePermissionMaps(PDO $pdo, int $tenantId, array $permIdsBySlug, ?\PDOStatement $link = null): void
    {
        $link ??= $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach (CommunityAccessProfiles::definitions() as $def) {
            $rid = self::roleId($pdo, $tenantId, $def['slug']);
            if (!$rid) {
                continue;
            }
            foreach (CommunityAccessProfiles::permissionSlugsFor($def['key']) as $p) {
                if (isset($permIdsBySlug[$p])) {
                    $link->execute([$rid, $permIdsBySlug[$p]]);
                }
            }
        }
    }

    /**
     * Garantit les trois profils d’accès (Membre, RH, Gestionnaire) et leurs habilitations.
     */
    public static function ensureAccessProfilesForTenant(PDO $pdo, int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $chkRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $insRole = $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        $updRole = $pdo->prepare(
            'UPDATE roles SET name = ?, description = ?, is_system = 1, is_locked = 1, role_layer = ? WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug')
        );
        foreach (CommunityAccessProfiles::definitions() as $def) {
            $chkRole->execute([$tenantId, $def['slug']]);
            if (!$chkRole->fetch()) {
                $insRole->execute([
                    $tenantId,
                    $def['name'],
                    $def['slug'],
                    $def['description'],
                    $def['is_system'],
                    $def['is_locked'],
                    $def['role_layer'],
                ]);
            } else {
                $updRole->execute([
                    $def['name'],
                    $def['description'],
                    $def['role_layer'],
                    $tenantId,
                    $def['slug'],
                ]);
            }
        }
        $permIdsBySlug = [];
        $q = $pdo->prepare('SELECT id, slug FROM permissions WHERE tenant_id = ?');
        $q->execute([$tenantId]);
        while ($pr = $q->fetch(PDO::FETCH_ASSOC)) {
            $permIdsBySlug[(string) $pr['slug']] = (int) $pr['id'];
        }
        self::applyDefaultRolePermissionMaps($pdo, $tenantId, $permIdsBySlug);
    }

    /**
     * @deprecated Utiliser {@see ensureAccessProfilesForTenant()}
     */
    public static function ensureOperationalRolesForTenant(PDO $pdo, int $tenantId): void
    {
        self::ensureAccessProfilesForTenant($pdo, $tenantId);
    }

    private static function permissionsTableHasActionColumn(PDO $pdo): bool
    {
        $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permissions' AND COLUMN_NAME = 'action' LIMIT 1");

        return (bool) ($st && $st->fetch());
    }

    private static function permissionsTableHasCodeColumn(PDO $pdo): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permissions' AND COLUMN_NAME = 'code' LIMIT 1");
        $cached = (bool) ($st && $st->fetch());

        return $cached;
    }

    private static function permissionsTableHasLabelColumn(PDO $pdo): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permissions' AND COLUMN_NAME = 'label' LIMIT 1");
        $cached = (bool) ($st && $st->fetch());

        return $cached;
    }

    /**
     * Insert permission for a tenant. Always fills `code` (= slug) when the column exists,
     * to satisfy uniq_permissions_tenant_code (empty code would collide on 2nd insert).
     *
     * @return int Permission id (existing or newly inserted)
     */
    private static function insertPermission(
        PDO $pdo,
        int $tenantId,
        string $name,
        string $slug,
        string $module,
        ?string $action = null,
        string $scope = 'community'
    ): int {
        $select = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $select->execute([$tenantId, $slug]);
        $existingId = (int) ($select->fetchColumn() ?: 0);
        if ($existingId > 0) {
            return $existingId;
        }

        $hasCode = self::permissionsTableHasCodeColumn($pdo);
        $hasLabel = self::permissionsTableHasLabelColumn($pdo);
        $hasAction = self::permissionsTableHasActionColumn($pdo) && $action !== null;

        $cols = ['tenant_id', 'name', 'slug', 'module'];
        $vals = [$tenantId, $name, $slug, $module];
        if ($hasCode) {
            $cols[] = 'code';
            $vals[] = $slug;
        }
        if ($hasLabel) {
            $cols[] = 'label';
            $vals[] = $name;
        }
        if ($hasAction) {
            $cols[] = 'action';
            $vals[] = $action;
        }
        // scope column is optional historically
        static $hasScope = null;
        if ($hasScope === null) {
            $stScope = $pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permissions' AND COLUMN_NAME = 'scope' LIMIT 1"
            );
            $hasScope = (bool) ($stScope && $stScope->fetchColumn());
        }
        if ($hasScope) {
            $cols[] = 'scope';
            $vals[] = $scope;
        }
        $cols[] = 'created_at';
        $placeholders = implode(', ', array_merge(array_fill(0, count($vals), '?'), ['NOW()']));
        $sql = 'INSERT INTO permissions (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')';

        try {
            $pdo->prepare($sql)->execute($vals);
            $id = (int) $pdo->lastInsertId();
            if ($id > 0) {
                return $id;
            }
        } catch (PDOException $e) {
            // Concurrent insert or unique(code) race: re-select
            if (!str_contains($e->getMessage(), 'Duplicate') && (string) $e->getCode() !== '23000') {
                throw $e;
            }
        }

        $select->execute([$tenantId, $slug]);
        $id = (int) ($select->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }

        // Last resort: match by code when slug missing
        if ($hasCode) {
            $byCode = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND code = ? LIMIT 1');
            $byCode->execute([$tenantId, $slug]);
            $id = (int) ($byCode->fetchColumn() ?: 0);
        }

        return $id;
    }

    private static function roleId(PDO $pdo, int $tenantId, string $slug): int
    {
        $st = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $st->execute([$tenantId, $slug]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return $r ? (int) $r['id'] : 0;
    }

    public static function ensurePersonnelPanelsAndMatricule(PDO $pdo, int $tenantId): void
    {
        $stmt = $pdo->query('SELECT 1 FROM personnel_admin_panels WHERE tenant_id = ' . (int) $tenantId . ' LIMIT 1');
        if ($stmt && $stmt->fetch()) {
            return;
        }
        $panels = [
            ['État civil', 'etat-civil', 'Identité et état civil', 10],
            ['Affectation', 'affectation', 'Unité, poste, affectation', 20],
            ['Formation', 'formation', 'Parcours et qualifications', 30],
            ['Sécurité / Clearance', 'securite', 'Niveaux de sécurité et habilitations', 40],
            ['Santé / Aptitude', 'sante', 'Aptitude médicale et restrictions', 50],
            ['Références / Notes', 'references-notes', 'Références et notes administratives', 60],
        ];
        foreach ($panels as $p) {
            $pdo->prepare('INSERT INTO personnel_admin_panels (tenant_id, name, slug, description, display_order) VALUES (?, ?, ?, ?, ?)')
                ->execute([$tenantId, $p[0], $p[1], $p[2], $p[3]]);
        }
        try {
            $pdo->prepare('INSERT IGNORE INTO tenant_matricule_config (tenant_id, prefix, format_pattern, next_number, updated_at) VALUES (?, ?, ?, ?, NOW())')->execute([$tenantId, 'ATH', '{prefix}-{seq:5}', 1]);
        } catch (PDOException) {
            // table absente ou déjà présent
        }
    }

    /**
     * Formation LMS « Parcours portail » (obligatoire, certifiante) — idempotent par slug.
     */
    public static function ensureOnboardingPortalCourse(PDO $pdo, int $tenantId, ?int $authorUserId = null): void
    {
        $path = dirname(__DIR__, 3) . '/bootstrap/training_onboarding_course_seed.php';
        if (!is_file($path)) {
            return;
        }
        require_once $path;
        run_training_onboarding_course_for_tenant($pdo, $tenantId, $authorUserId);
    }

    /**
     * Formation LMS « Parcours postes » (rôles, fonctions, spécialité, affectation) — idempotent par slug.
     */
    public static function ensureRolesOrgCourse(PDO $pdo, int $tenantId, ?int $authorUserId = null): void
    {
        $path = dirname(__DIR__, 3) . '/bootstrap/training_roles_org_course_seed.php';
        if (!is_file($path)) {
            return;
        }
        require_once $path;
        run_training_roles_org_course_for_tenant($pdo, $tenantId, $authorUserId);
    }

    /**
     * Formation LMS « Bureau recrutement » — idempotent par slug.
     */
    public static function ensureBureauRecrutementCourse(PDO $pdo, int $tenantId, ?int $authorUserId = null): void
    {
        $path = dirname(__DIR__, 3) . '/bootstrap/training_bureau_recrutement_course_seed.php';
        if (!is_file($path)) {
            return;
        }
        require_once $path;
        run_training_bureau_recrutement_course_for_tenant($pdo, $tenantId, $authorUserId);
    }

    /**
     * Formation LMS « ATAK Athena et Overwatch » (web + in-game) — idempotent par slug.
     */
    public static function ensureAtakCourse(PDO $pdo, int $tenantId, ?int $authorUserId = null): void
    {
        $path = dirname(__DIR__, 3) . '/bootstrap/training_atak_course_seed.php';
        if (!is_file($path)) {
            return;
        }
        require_once $path;
        run_training_atak_course_for_tenant($pdo, $tenantId, $authorUserId);
    }
}
