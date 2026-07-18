<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Authorization\TenantPermissionCatalog;
use App\Repositories\UnitRepository;
use App\Services\Rbac\MilitaryRoleCatalogSyncService;
use App\Services\Training\TenantPedagogyStructureService;
use PDO;
use PDOException;

/**
 * Reprise des seeds run-migrations.php pour un nouveau tenant (forum, documents, permissions admin de base).
 */
final class TenantSeedHelper
{
    public static function seedForumAndRoles(PDO $pdo, int $tenantId): void
    {
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, 'forum.view']);
        if ($stmt->fetch()) {
            self::ensurePedagogyMandatoryUnits($tenantId);

            return;
        }

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
        $insertPerm = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())');
        foreach ($permissions as $p) {
            $insertPerm->execute([$tenantId, $p[1], $p[0], $p[2]]);
            $permIds[$p[0]] = (int) $pdo->lastInsertId();
        }

        $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $adminRole->execute([$tenantId, 'tenant_admin']);
        $adminRoleId = (int) ($adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
        $coRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $coRole->execute([$tenantId, 'community_owner']);
        $communityOwnerId = (int) ($coRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
        if ($adminRoleId) {
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($permIds as $pid) {
                $link->execute([$adminRoleId, $pid]);
            }
        }
        if ($communityOwnerId) {
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($permIds as $pid) {
                $link->execute([$communityOwnerId, $pid]);
            }
        }

        foreach (TenantDefaultRoleDefinitions::operationalRoles() as $def) {
            $slug = $def['slug'];
            $st = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $st->execute([$tenantId, $slug]);
            if (!$st->fetch()) {
                $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
                    ->execute([
                        $tenantId,
                        $def['name'],
                        $slug,
                        $def['description'],
                        $def['is_system'],
                        $def['is_locked'],
                        $def['role_layer'],
                    ]);
            }
        }

        $modRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $modRole->execute([$tenantId, 'forum_moderator']);
        $modRoleId = (int) ($modRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
        if ($modRoleId) {
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach (['forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.moderate', 'forum.moderate_organization'] as $slug) {
                if (isset($permIds[$slug])) {
                    $link->execute([$modRoleId, $permIds[$slug]]);
                }
            }
        }

        $memberRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $memberRole->execute([$tenantId, 'member']);
        $memberRoleId = (int) ($memberRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
        if ($memberRoleId) {
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach (['forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own'] as $slug) {
                if (isset($permIds[$slug])) {
                    $link->execute([$memberRoleId, $permIds[$slug]]);
                }
            }
        }

        $stmt = $pdo->prepare('SELECT 1 FROM forum_categories WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        if ($stmt->fetch()) {
            try {
                MilitaryRoleCatalogSyncService::syncForTenant($pdo, $tenantId);
            } catch (\Throwable $_) {
            }
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

        try {
            MilitaryRoleCatalogSyncService::syncForTenant($pdo, $tenantId);
        } catch (\Throwable $_) {
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
        if (!$row) {
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
        $chkSlug = $pdo->prepare('SELECT 1 FROM forum_categories WHERE tenant_id = ? AND slug = ? LIMIT 1');
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
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, 'documents.view']);
        $docPermIds = [];
        if ($stmt->fetch()) {
            foreach ($docPermSlugs as $slug) {
                $s = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
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
            $insPerm = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())');
            foreach ($docPerms as $p) {
                $insPerm->execute([$tenantId, $p[1], $p[0], $p[2]]);
                $docPermIds[$p[0]] = (int) $pdo->lastInsertId();
            }
        }
        $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $adminRole->execute([$tenantId, 'tenant_admin']);
        $adminRoleId = (int) ($adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
        if ($adminRoleId && $docPermIds !== []) {
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($docPermIds as $pid) {
                $link->execute([$adminRoleId, $pid]);
            }
        }
        $memberRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $memberRole->execute([$tenantId, 'member']);
        $memberRoleId = (int) ($memberRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
        if ($memberRoleId && isset($docPermIds['documents.view'])) {
            $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$memberRoleId, $docPermIds['documents.view']]);
        }
        $officerRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $officerRole->execute([$tenantId, 'officer']);
        $officerRoleId = (int) ($officerRole->fetch(PDO::FETCH_ASSOC)['id'] ?? 0);
        if (!$officerRoleId) {
            $offDef = null;
            foreach (TenantDefaultRoleDefinitions::operationalRoles() as $r) {
                if (($r['slug'] ?? '') === 'officer') {
                    $offDef = $r;
                    break;
                }
            }
            if ($offDef !== null) {
                $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
                    ->execute([$tenantId, $offDef['name'], 'officer', $offDef['description'], 1, 0, 'intra']);
                $officerRoleId = (int) $pdo->lastInsertId();
            }
        }
        if ($officerRoleId) {
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach (['documents.view', 'documents.upload', 'documents.update'] as $slug) {
                if (isset($docPermIds[$slug])) {
                    $link->execute([$officerRoleId, $docPermIds[$slug]]);
                }
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
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, 'admin.organization']);
        if (!$stmt->fetch()) {
            $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())')->execute([$tenantId, 'Administration organisationnelle', 'admin.organization', 'admin']);
        }

        $stmt = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, 'community_owner']);
        if (!$stmt->fetch()) {
            $co = TenantDefaultRoleDefinitions::governanceRoles()[0];
            $pdo->prepare("INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, ?, ?, ?, 1, 1, 'community', NOW())")
                ->execute([$tenantId, $co['name'], $co['slug'], $co['description']]);
            $coId = (int) $pdo->lastInsertId();
            foreach (['admin.organization', 'admin.access'] as $permSlug) {
                $p = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
                $p->execute([$tenantId, $permSlug]);
                $permId = $p->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
                if ($permId) {
                    $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$coId, $permId]);
                }
            }
        }

        $tenantAdminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $tenantAdminRole->execute([$tenantId, 'tenant_admin']);
        $tenantAdminRoleId = $tenantAdminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        $permOrg = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $permOrg->execute([$tenantId, 'admin.organization']);
        $permOrgId = $permOrg->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        if ($tenantAdminRoleId && $permOrgId) {
            $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$tenantAdminRoleId, $permOrgId]);
        }

        $coR = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $coR->execute([$tenantId, 'community_owner']);
        $coRid = $coR->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        if ($coRid && $permOrgId) {
            $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([(int) $coRid, $permOrgId]);
        }

        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, 'training.view']);
        if (!$stmt->fetch()) {
            foreach ([['training.view', 'Voir les formations', 'training'], ['training.manage', 'Gérer les formations', 'training'], ['training.assign', 'Assigner des formations', 'training'], ['training.publications.manage', 'Gérer les publications de formation', 'training']] as $p) {
                $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())')->execute([$tenantId, $p[1], $p[0], $p[2]]);
            }
            foreach (['tenant_admin', 'community_owner'] as $roleSlug) {
                $adminRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
                $adminRole->execute([$tenantId, $roleSlug]);
                $adminRoleId = $adminRole->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
                if ($adminRoleId) {
                    $trainPerms = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug IN (\'training.view\',\'training.manage\',\'training.assign\',\'training.publications.manage\')');
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
        TenantDefaultRoleDefinitions::applyCanonicalLabels($pdo, $tenantId);

        $st = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        foreach (TenantDefaultRoleDefinitions::operationalRoles() as $def) {
            $st->execute([$tenantId, $def['slug']]);
            if (!$st->fetch()) {
                $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
                    ->execute([
                        $tenantId,
                        $def['name'],
                        $def['slug'],
                        $def['description'],
                        $def['is_system'],
                        $def['is_locked'],
                        $def['role_layer'],
                    ]);
            }
        }

        $permIds = [];
        $q = $pdo->prepare('SELECT id, slug FROM permissions WHERE tenant_id = ?');
        $q->execute([$tenantId]);
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $permIds[(string) $row['slug']] = (int) $row['id'];
        }

        $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        $roleId = static function (string $slug) use ($pdo, $tenantId): int {
            $s = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $s->execute([$tenantId, $slug]);
            $r = $s->fetch(PDO::FETCH_ASSOC);

            return $r ? (int) $r['id'] : 0;
        };

        foreach (TenantDefaultRoleDefinitions::defaultPermissionSlugsForOperationalRoles() as $slug => $permSlugs) {
            $rid = $roleId($slug);
            if (!$rid) {
                continue;
            }
            foreach ($permSlugs as $ps) {
                if (isset($permIds[$ps])) {
                    $link->execute([$rid, $permIds[$ps]]);
                }
            }
        }

        $inviteId = $roleId('invite');
        if ($inviteId && isset($permIds['forum.view'])) {
            $link->execute([$inviteId, $permIds['forum.view']]);
        }

        if ($template === 'standard') {
            $modId = $roleId('forum_moderator');
            if ($modId && isset($permIds['forum.moderate_organization'])) {
                $link->execute([$modId, $permIds['forum.moderate_organization']]);
            }
        }

        try {
            MilitaryRoleCatalogSyncService::syncForTenant($pdo, $tenantId);
        } catch (\Throwable $_) {
        }
    }

    /**
     * Rôles métier supplémentaires définis dans l’assistant (hors rôles système).
     *
     * @param list<array{name: string, slug: string, permission_slugs: list<string>}> $roles
     */
    public static function applyWizardCustomRoles(PDO $pdo, int $tenantId, array $roles): void
    {
        if ($roles === []) {
            return;
        }
        $permIds = [];
        $q = $pdo->prepare('SELECT id, slug FROM permissions WHERE tenant_id = ?');
        $q->execute([$tenantId]);
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $permIds[(string) $row['slug']] = (int) $row['id'];
        }
        $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        $insRole = $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, ?, ?, ?, 0, 0, \'intra\', NOW())');
        $chk = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');

        foreach ($roles as $r) {
            $name = trim((string) ($r['name'] ?? ''));
            $slug = trim((string) ($r['slug'] ?? ''));
            if ($name === '' || $slug === '') {
                continue;
            }
            $chk->execute([$tenantId, $slug]);
            if ($chk->fetch()) {
                continue;
            }
            $insRole->execute([$tenantId, $name, $slug, '']);
            $rid = (int) $pdo->lastInsertId();
            foreach ($r['permission_slugs'] ?? [] as $ps) {
                $ps = is_string($ps) ? trim($ps) : '';
                if ($ps === '' || !isset($permIds[$ps])) {
                    continue;
                }
                $link->execute([$rid, $permIds[$ps]]);
            }
        }
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
        $defs = TenantPermissionCatalog::definitions();
        if ($hasAction) {
            $insert = $pdo->prepare(
                'INSERT INTO permissions (tenant_id, name, slug, module, action, scope, created_at) VALUES (?, ?, ?, ?, ?, \'community\', NOW())'
            );
            $update = $pdo->prepare(
                'UPDATE permissions SET name = ?, module = ?, action = ? WHERE tenant_id = ? AND slug = ? LIMIT 1'
            );
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO permissions (tenant_id, name, slug, module, scope, created_at) VALUES (?, ?, ?, ?, \'community\', NOW())'
            );
            $update = $pdo->prepare(
                'UPDATE permissions SET name = ?, module = ? WHERE tenant_id = ? AND slug = ? LIMIT 1'
            );
        }

        $selectId = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
        foreach ($defs as $row) {
            $slug = $row['slug'];
            $selectId->execute([$tenantId, $slug]);
            $existing = $selectId->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                if ($hasAction) {
                    $update->execute([$row['name'], $row['module'], $row['action'], $tenantId, $slug]);
                } else {
                    $update->execute([$row['name'], $row['module'], $tenantId, $slug]);
                }
                continue;
            }
            if ($hasAction) {
                $insert->execute([$tenantId, $row['name'], $slug, $row['module'], $row['action']]);
            } else {
                $insert->execute([$tenantId, $row['name'], $slug, $row['module']]);
            }
        }

        $permIdsBySlug = [];
        $q = $pdo->prepare('SELECT id, slug FROM permissions WHERE tenant_id = ?');
        $q->execute([$tenantId]);
        while ($pr = $q->fetch(PDO::FETCH_ASSOC)) {
            $permIdsBySlug[(string) $pr['slug']] = (int) $pr['id'];
        }

        $chkRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $insRole = $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        foreach (TenantDefaultRoleDefinitions::operationalRoles() as $def) {
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
            }
        }

        $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach (['tenant_admin', 'community_owner'] as $roleSlug) {
            $rid = self::roleId($pdo, $tenantId, $roleSlug);
            if (!$rid) {
                continue;
            }
            foreach ($permIdsBySlug as $pid) {
                $link->execute([$rid, $pid]);
            }
        }

        $modId = self::roleId($pdo, $tenantId, 'forum_moderator');
        if ($modId) {
            $modSlugs = array_unique(array_merge(
                [
                    'forum.view',
                    'forum.create_topic',
                    'forum.reply',
                    'forum.edit_own',
                    'forum.delete_own',
                    'forum.moderate',
                    'forum.moderate_organization',
                    'forum.categories.manage',
                    'forum.manage_categories',
                    'interteam.missions.respond',
                ],
                TenantPermissionCatalog::forumModerateGranularSlugs()
            ));
            foreach ($modSlugs as $ms) {
                if (isset($permIdsBySlug[$ms])) {
                    $link->execute([$modId, $permIdsBySlug[$ms]]);
                }
            }
        }

        foreach (TenantDefaultRoleDefinitions::defaultPermissionSlugsForOperationalRoles() as $slug => $permSlugs) {
            $rid = self::roleId($pdo, $tenantId, $slug);
            if (!$rid) {
                continue;
            }
            foreach ($permSlugs as $p) {
                if (isset($permIdsBySlug[$p])) {
                    $link->execute([$rid, $permIdsBySlug[$p]]);
                }
            }
        }

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
    }

    /**
     * Crée les rôles opérationnels manquants (nouveaux slugs ajoutés au catalogue) et rattache les permissions par défaut.
     * Idempotent — utile pour les tenants créés avant l’extension du jeu de rôles (chaîne pédagogique).
     */
    public static function ensureOperationalRolesForTenant(PDO $pdo, int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $chkRole = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $insRole = $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        foreach (TenantDefaultRoleDefinitions::operationalRoles() as $def) {
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
            }
        }
        $permIdsBySlug = [];
        $q = $pdo->prepare('SELECT id, slug FROM permissions WHERE tenant_id = ?');
        $q->execute([$tenantId]);
        while ($pr = $q->fetch(PDO::FETCH_ASSOC)) {
            $permIdsBySlug[(string) $pr['slug']] = (int) $pr['id'];
        }
        $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach (TenantDefaultRoleDefinitions::defaultPermissionSlugsForOperationalRoles() as $roleSlug => $permSlugs) {
            $rid = self::roleId($pdo, $tenantId, $roleSlug);
            if (!$rid) {
                continue;
            }
            foreach ($permSlugs as $p) {
                if (isset($permIdsBySlug[$p])) {
                    $link->execute([$rid, $permIdsBySlug[$p]]);
                }
            }
        }
    }

    private static function permissionsTableHasActionColumn(PDO $pdo): bool
    {
        $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permissions' AND COLUMN_NAME = 'action' LIMIT 1");

        return (bool) ($st && $st->fetch());
    }

    private static function roleId(PDO $pdo, int $tenantId, string $slug): int
    {
        $st = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
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
}
