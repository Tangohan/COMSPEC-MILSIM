<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Community\TenantDefaultRoleDefinitions;
use PDO;

class RoleRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function allForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM roles WHERE tenant_id = ? ORDER BY name ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Rôles communauté + intra (exclut toute logique site). */
    /** @return list<array<string, mixed>> */
    public function forTenantOrganization(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM roles WHERE tenant_id = ? AND role_layer IN ('community','intra')"
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return TenantDefaultRoleDefinitions::sortOrganizationRoleRows($rows);
    }

    /** @return list<array<string, mixed>> */
    public function forTenantByLayer(int $tenantId, string $layer): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM roles WHERE tenant_id = ? AND role_layer = ?'
        );
        $stmt->execute([$tenantId, $layer]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return TenantDefaultRoleDefinitions::sortOrganizationRoleRows($rows);
    }

    /** Rôles site globaux (tenant_id NULL). */
    /** @return list<array<string, mixed>> */
    public function allSiteRoles(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM roles WHERE tenant_id IS NULL AND role_layer = 'site' ORDER BY name ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Rôles organisation (communauté/intra) attribués, pour un lot d’utilisateurs, avec styles d’affichage
     * (badge_style, semantic_tier). Repli sur le rôle unique legacy (`users.role_id`) transmis via
     * `$legacyRoleIdByUserId` quand aucune ligne multi-rôles n’existe pour l’utilisateur.
     *
     * Deux requêtes batchées au maximum (tenant_user_roles puis user_roles), jamais une par utilisateur.
     *
     * @param list<int> $userIds
     * @param array<int,int|null> $legacyRoleIdByUserId
     * @return array<int, list<array<string,mixed>>>
     */
    public function listOrganizationRolesForUsers(int $tenantId, array $userIds, array $legacyRoleIdByUserId = []): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $v): bool => $v > 0)));
        if ($tenantId < 1 || $userIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $roleCols = "r.id, r.name, r.slug, r.role_layer, r.badge_style,
            COALESCE(r.semantic_tier, 'function') AS semantic_tier,
            COALESCE(r.display_priority, 0) AS display_priority";

        $byUser = [];
        $seenPair = [];
        $addRow = static function (array &$byUser, array &$seenPair, int $uid, array $role): void {
            $rid = (int) ($role['id'] ?? 0);
            if ($rid < 1 || isset($seenPair[$uid][$rid])) {
                return;
            }
            $seenPair[$uid][$rid] = true;
            $byUser[$uid][] = $role;
        };

        try {
            $stmt = $this->pdo->prepare(
                "SELECT tur.user_id AS __uid, {$roleCols}
                 FROM tenant_user_roles tur
                 INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = ?
                 WHERE tur.tenant_id = ? AND tur.org_unit_id IS NULL AND tur.user_id IN ({$placeholders})"
            );
            $stmt->execute(array_merge([$tenantId, $tenantId], $userIds));
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $uid = (int) ($row['__uid'] ?? 0);
                unset($row['__uid']);
                $addRow($byUser, $seenPair, $uid, $row);
            }
        } catch (\Throwable) {
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT ur.user_id AS __uid, {$roleCols}
                 FROM user_roles ur
                 INNER JOIN roles r ON r.id = ur.role_id AND r.tenant_id = ?
                 WHERE ur.user_id IN ({$placeholders})"
            );
            $stmt->execute(array_merge([$tenantId], $userIds));
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $uid = (int) ($row['__uid'] ?? 0);
                unset($row['__uid']);
                $addRow($byUser, $seenPair, $uid, $row);
            }
        } catch (\Throwable) {
        }

        $missingByRoleId = [];
        foreach ($userIds as $uid) {
            if (!empty($byUser[$uid])) {
                continue;
            }
            $legacy = (int) ($legacyRoleIdByUserId[$uid] ?? 0);
            if ($legacy > 0) {
                $missingByRoleId[$legacy][] = $uid;
            }
        }
        if ($missingByRoleId !== []) {
            $roleIds = array_keys($missingByRoleId);
            $ph2 = implode(',', array_fill(0, count($roleIds), '?'));
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT {$roleCols} FROM roles r WHERE r.tenant_id = ? AND r.id IN ({$ph2})"
                );
                $stmt->execute(array_merge([$tenantId], $roleIds));
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $rid = (int) ($row['id'] ?? 0);
                    foreach ($missingByRoleId[$rid] ?? [] as $uid) {
                        $addRow($byUser, $seenPair, $uid, $row);
                    }
                }
            } catch (\Throwable) {
            }
        }

        $tierRank = static function (array $x): int {
            return match ((string) ($x['semantic_tier'] ?? 'function')) {
                'authority' => 1,
                'function' => 2,
                'liaison' => 3,
                'support' => 4,
                'specialty' => 5,
                'status' => 6,
                default => 2,
            };
        };
        foreach ($byUser as &$rows) {
            usort($rows, static function (array $a, array $b) use ($tierRank): int {
                $cmp = $tierRank($a) <=> $tierRank($b);
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmp = ((int) ($a['display_priority'] ?? 0)) <=> ((int) ($b['display_priority'] ?? 0));
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            });
        }
        unset($rows);

        return $byUser;
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM roles WHERE id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return int|null ID du rôle par slug dans le tenant. */
    public function getIdBySlug(int $tenantId, string $slug): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_COLUMN);

        return $row !== false ? (int) $row : null;
    }

    /** Attribution depuis l’admin communauté : pas de rôle site / global. */
    public function canAssignInTenantAdminContext(int $roleId, int $tenantId): bool
    {
        $r = $this->findById($roleId, $tenantId);
        if (!$r) {
            return false;
        }
        $layer = (string) ($r['role_layer'] ?? 'community');

        return $layer === 'community' || $layer === 'intra';
    }

    /**
     * Matrice permissions × rôles organisation (pour UI admin).
     *
     * @return array{roles: list<array<string,mixed>>, permissions: list<array<string,mixed>>, byRole: array<int, array<int, true>>}
     */
    public function organizationRolesPermissionMatrix(int $tenantId): array
    {
        $roles = $this->forTenantOrganization($tenantId);
        $roleIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $roles);
        $roleIds = array_values(array_filter($roleIds, static fn (int $id): bool => $id > 0));
        if ($roleIds === []) {
            return ['roles' => [], 'permissions' => [], 'byRole' => []];
        }
        $ph = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT p.id, p.slug, p.name, COALESCE(p.module, '') AS module
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id IN ({$ph})
             ORDER BY p.module, p.name"
        );
        $stmt->execute($roleIds);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $byRole = [];
        $stmt2 = $this->pdo->prepare(
            "SELECT rp.role_id, p.id AS permission_id
             FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id IN ({$ph})"
        );
        $stmt2->execute($roleIds);
        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $rid = (int) ($row['role_id'] ?? 0);
            $pid = (int) ($row['permission_id'] ?? 0);
            if ($rid > 0 && $pid > 0) {
                $byRole[$rid][$pid] = true;
            }
        }

        return ['roles' => $roles, 'permissions' => $permissions, 'byRole' => $byRole];
    }

    /**
     * Met à jour l’intitulé et la description d’un rôle d’organisation (slug inchangé).
     * Rôles critiques ou « gestionnaire d’organisation » : description seule.
     */
    public function updateOrganizationRolePresentation(int $tenantId, int $roleId, string $name, string $description): bool
    {
        $r = $this->findById($roleId, $tenantId);
        if (!$r) {
            return false;
        }
        $slug = (string) ($r['slug'] ?? '');
        $critical = !empty($r['is_system_critical']);
        $desc = mb_substr(trim($description), 0, 500);
        if ($critical || $slug === 'community_owner') {
            $st = $this->pdo->prepare('UPDATE roles SET description = ? WHERE id = ? AND tenant_id = ?');

            return $st->execute([$desc, $roleId, $tenantId]);
        }
        $nm = mb_substr(trim($name), 0, 160);
        if ($nm === '') {
            return false;
        }
        $st = $this->pdo->prepare('UPDATE roles SET name = ?, description = ? WHERE id = ? AND tenant_id = ?');

        return $st->execute([$nm, $desc, $roleId, $tenantId]);
    }

    /**
     * @param array{color?: string, icon?: string, variant?: string}|null $style
     */
    public function updateOrganizationRoleBadgeStyle(int $tenantId, int $roleId, ?array $style): bool
    {
        $r = $this->findById($roleId, $tenantId);
        if (!$r) {
            return false;
        }
        if ($style === null || $style === []) {
            $st = $this->pdo->prepare('UPDATE roles SET badge_style = NULL WHERE id = ? AND tenant_id = ?');

            return $st->execute([$roleId, $tenantId]);
        }
        $clean = array_filter(
            [
                'color' => isset($style['color']) ? trim((string) $style['color']) : '',
                'icon' => isset($style['icon']) ? trim((string) $style['icon']) : '',
                'variant' => isset($style['variant']) ? trim((string) $style['variant']) : '',
            ],
            static fn (string $v): bool => $v !== ''
        );
        if ($clean === []) {
            $st = $this->pdo->prepare('UPDATE roles SET badge_style = NULL WHERE id = ? AND tenant_id = ?');

            return $st->execute([$roleId, $tenantId]);
        }
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $st = $this->pdo->prepare('UPDATE roles SET badge_style = ? WHERE id = ? AND tenant_id = ?');

        return $st->execute([$json !== false ? $json : '{}', $roleId, $tenantId]);
    }

    /**
     * Crée le rôle tenant aligné sur le catalogue global (role_definitions) s’il manque encore.
     * Utilisé pour la chaîne pédagogique (slugs catalogue non livrés dans le jeu minimal opérationnel).
     */
    public function ensureCatalogRoleForTenant(int $tenantId, string $slug): ?int
    {
        $slug = trim($slug);
        if ($tenantId < 1 || $slug === '') {
            return null;
        }
        $existing = $this->getIdBySlug($tenantId, $slug);
        if ($existing !== null) {
            return $existing;
        }
        try {
            $chk = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_definitions' LIMIT 1");
            if (!$chk || !$chk->fetchColumn()) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT id, name_fr, description FROM role_definitions WHERE slug = ? LIMIT 1');
        $st->execute([$slug]);
        $def = $st->fetch(PDO::FETCH_ASSOC);
        if (!$def) {
            return null;
        }
        $defId = (int) ($def['id'] ?? 0);
        if ($defId < 1) {
            return null;
        }
        $name = mb_substr(trim((string) ($def['name_fr'] ?? $slug)), 0, 160);
        $desc = isset($def['description']) ? mb_substr(trim((string) $def['description']), 0, 500) : null;
        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, definition_id, created_at)
                 VALUES (?, ?, ?, ?, 1, 0, \'intra\', ?, NOW())'
            );
            $ins->execute([$tenantId, $name, $slug, $desc, $defId]);

            return (int) $this->pdo->lastInsertId();
        } catch (\Throwable) {
            return $this->getIdBySlug($tenantId, $slug);
        }
    }
}
