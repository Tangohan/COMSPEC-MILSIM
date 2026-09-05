<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Authorization\SystemReservedPermissions;
use App\Core\Database;
use App\Core\Gate;
use App\Repositories\UserRepository;
use PDO;

class RbacService
{
    private PDO $pdo;

    private static ?bool $permissionsHasRbacScope = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function permissionsTableHasRbacScope(): bool
    {
        if (self::$permissionsHasRbacScope === null) {
            $stmt = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permissions' AND COLUMN_NAME = 'rbac_scope' LIMIT 1"
            );
            self::$permissionsHasRbacScope = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$permissionsHasRbacScope;
    }

    private function userHasTenantUserRoleRows(int $userId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM tenant_user_roles WHERE user_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$userId, $tenantId]);

        return (bool) $stmt->fetchColumn();
    }

    public function loadPermissionsForRole(?int $roleId): array
    {
        if (!$roleId) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT p.slug FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?'
        );
        $stmt->execute([$roleId]);
        $slugs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Les rôles chargés ici relèvent d’une communauté : les habilitations plateforme
        // ne peuvent jamais en provenir, même si une ligne héritée les rattache encore.
        return SystemReservedPermissions::filter(is_array($slugs) ? array_map('strval', $slugs) : []);
    }

    /**
     * @param list<int> $roleIds
     * @return list<string>
     */
    public function loadPermissionsForRoles(array $roleIds): array
    {
        $slugs = [];
        foreach (array_unique(array_values(array_filter(array_map('intval', $roleIds), static fn (int $id): bool => $id > 0))) as $rid) {
            $slugs = array_merge($slugs, $this->loadPermissionsForRole($rid));
        }

        return array_values(array_unique(array_map('strval', $slugs)));
    }

    /**
     * @return list<string>
     */
    public function loadSitePermissionsForEmail(?string $email): array
    {
        if ($email === null || trim($email) === '') {
            return [];
        }
        $email = strtolower(trim($email));
        $roleIds = $this->fetchSiteRoleIdsForEmail($email);
        if ($roleIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT p.slug FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id IN ($placeholders)"
        );
        $stmt->execute($roleIds);
        $slugs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($slugs)) {
            return [];
        }

        return array_values(array_unique(array_map('strval', $slugs)));
    }

    /** @return list<int> */
    private function fetchSiteRoleIdsForEmail(string $emailNormalized): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sra.role_id FROM site_role_assignments sra
             INNER JOIN roles r ON r.id = sra.role_id AND r.tenant_id IS NULL AND r.role_layer = \'site\'
             WHERE sra.email_normalized = ? AND sra.revoked_at IS NULL'
        );
        $stmt->execute([$emailNormalized]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param list<int> $tenantRoleIds
     */
    public function setPermissionsForGate(array $tenantRoleIds, ?string $userEmail = null): void
    {
        $tenantPerms = $this->loadPermissionsForRoles($tenantRoleIds);
        $sitePerms = $this->loadSitePermissionsForEmail($userEmail);
        $merged = array_values(array_unique([...$tenantPerms, ...$sitePerms]));
        Gate::getInstance()->setPermissions($merged);
    }

    /**
     * @param array<string, mixed> $user Ligne utilisateur (id, role_id, email, tenant_id).
     */
    public function setPermissionsForGateFromUserRow(array $user, UserRepository $users): void
    {
        $uid = (int) ($user['id'] ?? 0);
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $legacy = isset($user['role_id']) && $user['role_id'] !== null && $user['role_id'] !== ''
            ? (int) $user['role_id']
            : null;

        if ($users->hasTenantUserRolesTable() && $this->userHasTenantUserRoleRows($uid, $tenantId)) {
            [$flat, $unitMap] = $this->computeFlatAndUnitMapsFromTenantUserRoles($uid, $tenantId);
            [$flat, $unitMap] = $this->applyUserPermissionOverrides($uid, $tenantId, $flat, $unitMap);
            if ($flat === [] && $unitMap === []) {
                $ids = $users->tenantRoleIdsForRbac($uid, $legacy);
                $this->setPermissionsForGate($ids, (string) ($user['email'] ?? ''));

                return;
            }
            $sitePerms = $this->loadSitePermissionsForEmail((string) ($user['email'] ?? ''));
            $flat = array_values(array_unique([...$flat, ...$sitePerms]));
            Gate::getInstance()->setFullRbacState($flat, $unitMap);

            return;
        }

        $ids = $users->tenantRoleIdsForRbac($uid, $legacy);
        $this->setPermissionsForGate($ids, (string) ($user['email'] ?? ''));
    }

    /**
     * @return array{0: list<string>, 1: array<string, list<int>>}
     */
    private function computeFlatAndUnitMapsFromTenantUserRoles(int $userId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT role_id, org_unit_id FROM tenant_user_roles WHERE user_id = ? AND tenant_id = ?'
        );
        $stmt->execute([$userId, $tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $flat = [];
        $unitMap = [];

        $permStmt = $this->pdo->prepare(
            $this->permissionsTableHasRbacScope()
                ? 'SELECT p.slug, p.rbac_scope AS rs FROM permissions p
                   INNER JOIN role_permissions rp ON rp.permission_id = p.id
                   WHERE rp.role_id = ?'
                : 'SELECT p.slug, p.scope AS rs FROM permissions p
                   INNER JOIN role_permissions rp ON rp.permission_id = p.id
                   WHERE rp.role_id = ?'
        );

        foreach ($rows as $row) {
            $roleId = (int) ($row['role_id'] ?? 0);
            $orgUnitId = isset($row['org_unit_id']) && $row['org_unit_id'] !== null && $row['org_unit_id'] !== ''
                ? (int) $row['org_unit_id']
                : null;
            if ($roleId < 1) {
                continue;
            }
            $permStmt->execute([$roleId]);
            while ($p = $permStmt->fetch(PDO::FETCH_ASSOC)) {
                $slug = (string) ($p['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }
                // `tenant_user_roles` ne porte que des rôles de communauté : une habilitation
                // plateforme rattachée là est ignorée, en périmètre global comme unitaire.
                if (SystemReservedPermissions::isReserved($slug)) {
                    continue;
                }
                $rs = $this->normalizeRbacScope((string) ($p['rs'] ?? 'tenant'));
                $target = PermissionScopeResolver::resolve($rs, $orgUnitId);
                if ($target['flat']) {
                    $flat[] = $slug;
                } elseif ($target['unit_id'] !== null) {
                    if (!isset($unitMap[$slug])) {
                        $unitMap[$slug] = [];
                    }
                    $unitMap[$slug][] = $target['unit_id'];
                }
            }
        }

        foreach ($unitMap as $slug => $ids) {
            $unitMap[$slug] = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $x): bool => $x > 0)));
        }

        return [array_values(array_unique($flat)), $unitMap];
    }

    private function normalizeRbacScope(string $raw): string
    {
        if ($this->permissionsTableHasRbacScope()) {
            $raw = strtolower(trim($raw));

            return in_array($raw, ['global', 'tenant', 'unit'], true) ? $raw : 'tenant';
        }
        $raw = strtolower(trim($raw));

        return match ($raw) {
            'site' => 'global',
            'community' => 'tenant',
            default => 'unit',
        };
    }

    /**
     * @param list<string> $flat
     * @param array<string, list<int>> $unitMap
     * @return array{0: list<string>, 1: array<string, list<int>>}
     */
    private function applyUserPermissionOverrides(int $userId, int $tenantId, array $flat, array $unitMap): array
    {
        $chk = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_permission_overrides' LIMIT 1");
        if (!$chk || !$chk->fetchColumn()) {
            return [$flat, $unitMap];
        }
        $st = $this->pdo->prepare(
            'SELECT p.slug, o.grant_flag, o.org_unit_id FROM user_permission_overrides o
             INNER JOIN permissions p ON p.id = o.permission_id
             WHERE o.user_id = ? AND o.tenant_id = ?'
        );
        $st->execute([$userId, $tenantId]);
        $flatSet = array_flip($flat);
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $slug = (string) ($r['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $grant = !empty($r['grant_flag']);
            // Une surcharge utilisateur est rattachée à un tenant : elle ne peut pas accorder
            // une habilitation plateforme. Le retrait (`grant_flag` faux) reste sans objet ici,
            // puisque ces slugs n’entrent jamais dans l’ensemble.
            if ($grant && SystemReservedPermissions::isReserved($slug)) {
                continue;
            }
            $ou = isset($r['org_unit_id']) && $r['org_unit_id'] !== null && $r['org_unit_id'] !== ''
                ? (int) $r['org_unit_id']
                : null;
            if ($ou === null || $ou < 1) {
                if ($grant) {
                    $flatSet[$slug] = true;
                } else {
                    unset($flatSet[$slug]);
                }
            } else {
                if ($grant) {
                    if (!isset($unitMap[$slug])) {
                        $unitMap[$slug] = [];
                    }
                    $unitMap[$slug][] = $ou;
                } else {
                    if (isset($unitMap[$slug])) {
                        $unitMap[$slug] = array_values(array_diff($unitMap[$slug], [$ou]));
                        if ($unitMap[$slug] === []) {
                            unset($unitMap[$slug]);
                        }
                    }
                }
            }
        }

        return [array_keys($flatSet), $unitMap];
    }
}
