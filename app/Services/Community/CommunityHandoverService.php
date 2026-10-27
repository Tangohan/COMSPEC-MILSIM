<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Database;
use App\Support\SqlText;
use PDO;
use Throwable;

/**
 * Communautés d’un compte qui disparaît : qui les reprend.
 *
 * Supprimer le compte du seul propriétaire laissait la communauté sans responsable, et
 * l’ancien compte restait accroché à tout ce qu’elle contient. Avant toute suppression :
 *
 * - un autre propriétaire actif existe : rien ne change, sinon `tenants.owner_user_id`
 *   qui passe sur lui si c’était le compte supprimé ;
 * - sinon le membre actif suivant reçoit le rôle propriétaire : d’abord les cadres
 *   (adjoint, administrateur, officiers), puis le plus ancien membre ;
 * - sinon (personne d’autre) la communauté est archivée puis supprimée avec ses données.
 *
 * Les autres membres ne perdent rien : seule la communauté vide disparaît.
 */
final class CommunityHandoverService
{
    public const OWNER_ROLE_SLUG = 'community_owner';

    /** Ordre de reprise : le premier rôle trouvé l’emporte, puis l’ancienneté. */
    private const SUCCESSOR_ROLE_PRIORITY = [
        'deputy_commander',
        'tenant_admin',
        'technical_admin',
        'operations_officer',
        'officer',
    ];

    /** @var array<string, bool> */
    private array $tables = [];

    /** @var array<string, bool> */
    private array $columns = [];

    /**
     * @param (callable(int, string): array{ok: bool, error?: string})|null $removeCommunity
     *        Suppression d’une communauté restée vide (tenant id, slug). Par défaut
     *        {@see TenantRemovalService} : archivage puis suppression définitive.
     */
    private bool $injectedPdo;

    public function __construct(
        private ?PDO $pdo = null,
        private $removeCommunity = null,
    ) {
        $this->injectedPdo = $pdo !== null;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * Ce que la suppression des comptes donnés ferait à leurs communautés, sans rien changer.
     *
     * @param list<int> $departingIds toutes les fiches de la personne (même e-mail)
     * @return list<array{tenant_id: int, tenant_name: string, action: string, successor_id: ?int, successor_name: ?string}>
     *         action : keep (autre propriétaire), delegate (rôle transmis), delete (communauté vide)
     */
    public function plan(array $departingIds, ?int $onlyTenantId = null): array
    {
        $departing = $this->cleanIds($departingIds);
        if ($departing === []) {
            return [];
        }

        $out = [];
        foreach ($this->ownedTenants($departing) as $tenant) {
            $tenantId = (int) $tenant['id'];
            if ($onlyTenantId !== null && $tenantId !== $onlyTenantId) {
                continue;
            }
            if ($this->isPlaceholder($tenant)) {
                continue;
            }
            $roleId = $this->ownerRoleId($tenantId);
            $others = array_values(array_diff($this->activeOwnerIds($tenantId, $roleId), $departing));
            if ($others !== []) {
                $out[] = $this->planRow($tenant, 'keep', $others[0]);
                continue;
            }
            $successor = $this->pickSuccessor($tenantId, $departing);
            $out[] = $this->planRow($tenant, $successor !== null ? 'delegate' : 'delete', $successor);
        }

        return $out;
    }

    /**
     * Applique le plan : transmet le rôle propriétaire, ou supprime la communauté vide.
     *
     * @param list<int> $departingIds
     * @return list<array{tenant_id: int, tenant_name: string, action: string, successor_id: ?int, successor_name: ?string, ok: bool, error?: string}>
     */
    public function handOver(array $departingIds, ?int $onlyTenantId = null): array
    {
        $departing = $this->cleanIds($departingIds);
        $results = [];
        foreach ($this->plan($departing, $onlyTenantId) as $row) {
            $tenantId = $row['tenant_id'];
            try {
                if ($row['action'] === 'keep') {
                    $this->moveOwnerColumn($tenantId, $departing, (int) $row['successor_id']);
                    $results[] = $row + ['ok' => true];
                    continue;
                }
                if ($row['action'] === 'delegate') {
                    $successorId = (int) $row['successor_id'];
                    $roleId = $this->ownerRoleId($tenantId);
                    if ($roleId > 0) {
                        $this->grantRole($successorId, $tenantId, $roleId);
                    }
                    $this->moveOwnerColumn($tenantId, $departing, $successorId);
                    $this->announce($tenantId, (string) $row['successor_name']);
                    $results[] = $row + ['ok' => true];
                    continue;
                }

                $slug = (string) ($this->tenantRow($tenantId)['slug'] ?? '');
                $removed = $this->removeCommunity($tenantId, $slug);
                $results[] = $row + ['ok' => (bool) ($removed['ok'] ?? false)]
                    + (($removed['ok'] ?? false) ? [] : ['error' => (string) ($removed['error'] ?? 'suppression refusée')]);
            } catch (Throwable $e) {
                $results[] = $row + ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $tenant
     * @return array{tenant_id: int, tenant_name: string, action: string, successor_id: ?int, successor_name: ?string}
     */
    private function planRow(array $tenant, string $action, ?int $successorId): array
    {
        return [
            'tenant_id' => (int) $tenant['id'],
            'tenant_name' => trim((string) ($tenant['name'] ?? '')) ?: ('#' . (int) $tenant['id']),
            'action' => $action,
            'successor_id' => $successorId,
            'successor_name' => $successorId !== null ? $this->userLabel($successorId) : null,
        ];
    }

    /**
     * Communautés dont l’un des comptes est propriétaire (rôle ou colonne owner_user_id).
     *
     * @param list<int> $departing
     * @return list<array<string, mixed>>
     */
    private function ownedTenants(array $departing): array
    {
        $in = implode(',', array_fill(0, count($departing), '?'));
        $tenantIds = [];

        if ($this->hasColumn('tenants', 'owner_user_id')) {
            foreach ($this->rows("SELECT id FROM tenants WHERE owner_user_id IN ({$in})", $departing) as $r) {
                $tenantIds[] = (int) $r['id'];
            }
        }

        $roles = $this->rows(
            'SELECT id, tenant_id FROM roles WHERE ' . SqlText::equals($this->pdo(), 'slug') . ' AND tenant_id IS NOT NULL',
            [self::OWNER_ROLE_SLUG]
        );
        $roleTenant = [];
        foreach ($roles as $r) {
            $roleTenant[(int) $r['id']] = (int) $r['tenant_id'];
        }
        if ($roleTenant !== []) {
            $roleIn = implode(',', array_keys($roleTenant));
            foreach ($this->rows("SELECT role_id FROM users WHERE id IN ({$in}) AND role_id IN ({$roleIn})", $departing) as $r) {
                $tenantIds[] = $roleTenant[(int) $r['role_id']] ?? 0;
            }
            if ($this->hasTable('user_community_profiles')) {
                foreach ($this->rows("SELECT role_id FROM user_community_profiles WHERE user_id IN ({$in}) AND role_id IN ({$roleIn})", $departing) as $r) {
                    $tenantIds[] = $roleTenant[(int) $r['role_id']] ?? 0;
                }
            }
            if ($this->hasTable('user_roles')) {
                foreach ($this->rows("SELECT role_id FROM user_roles WHERE user_id IN ({$in}) AND role_id IN ({$roleIn})", $departing) as $r) {
                    $tenantIds[] = $roleTenant[(int) $r['role_id']] ?? 0;
                }
            }
            if ($this->hasTable('tenant_user_roles')) {
                foreach ($this->rows("SELECT role_id FROM tenant_user_roles WHERE user_id IN ({$in}) AND role_id IN ({$roleIn}) AND org_unit_id IS NULL", $departing) as $r) {
                    $tenantIds[] = $roleTenant[(int) $r['role_id']] ?? 0;
                }
            }
        }

        $tenantIds = array_values(array_unique(array_filter($tenantIds, static fn (int $id): bool => $id > 0)));
        sort($tenantIds);
        $out = [];
        foreach ($tenantIds as $id) {
            $row = $this->tenantRow($id);
            if ($row !== null) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * Propriétaires encore actifs (rôle principal ou additionnel), comptes techniques exclus.
     *
     * @return list<int>
     */
    private function activeOwnerIds(int $tenantId, int $roleId): array
    {
        $ids = [];
        if ($roleId > 0) {
            foreach ($this->rows('SELECT id FROM users WHERE role_id = ?', [$roleId]) as $r) {
                $ids[] = (int) $r['id'];
            }
            if ($this->hasTable('user_community_profiles')) {
                foreach ($this->rows('SELECT user_id FROM user_community_profiles WHERE role_id = ? AND tenant_id = ?', [$roleId, $tenantId]) as $r) {
                    $ids[] = (int) $r['user_id'];
                }
            }
            if ($this->hasTable('user_roles')) {
                foreach ($this->rows('SELECT user_id FROM user_roles WHERE role_id = ?', [$roleId]) as $r) {
                    $ids[] = (int) $r['user_id'];
                }
            }
            if ($this->hasTable('tenant_user_roles')) {
                foreach ($this->rows('SELECT user_id FROM tenant_user_roles WHERE role_id = ? AND tenant_id = ? AND org_unit_id IS NULL', [$roleId, $tenantId]) as $r) {
                    $ids[] = (int) $r['user_id'];
                }
            }
        }
        $ids = array_values(array_unique($ids));
        $members = array_flip($this->activeHumanMemberIds($tenantId));

        return array_values(array_filter($ids, static fn (int $id): bool => isset($members[$id])));
    }

    /**
     * Le membre qui reprend la communauté : cadres d’abord, puis le plus ancien.
     *
     * @param list<int> $departing
     */
    private function pickSuccessor(int $tenantId, array $departing): ?int
    {
        $candidates = array_values(array_diff($this->activeHumanMemberIds($tenantId), $departing));
        if ($candidates === []) {
            return null;
        }

        $rank = [];
        foreach ($candidates as $id) {
            $rank[$id] = count(self::SUCCESSOR_ROLE_PRIORITY);
        }
        $slugRank = array_flip(self::SUCCESSOR_ROLE_PRIORITY);
        foreach ($this->roleSlugsFor($tenantId, $candidates) as $userId => $slugs) {
            foreach ($slugs as $slug) {
                if (isset($slugRank[$slug]) && $slugRank[$slug] < $rank[$userId]) {
                    $rank[$userId] = $slugRank[$slug];
                }
            }
        }
        $joined = $this->joinedAt($tenantId, $candidates);

        usort($candidates, static function (int $a, int $b) use ($rank, $joined): int {
            return [$rank[$a], $joined[$a] ?? '9999', $a] <=> [$rank[$b], $joined[$b] ?? '9999', $b];
        });

        return $candidates[0];
    }

    /**
     * Membres actifs, humains, sans suppression en cours.
     *
     * @return list<int>
     */
    private function activeHumanMemberIds(int $tenantId): array
    {
        $ids = [];
        foreach ($this->rows('SELECT id FROM users WHERE tenant_id = ?', [$tenantId]) as $r) {
            $ids[] = (int) $r['id'];
        }
        if ($this->hasTable('user_community_memberships')) {
            foreach ($this->rows("SELECT user_id FROM user_community_memberships WHERE tenant_id = ? AND status = 'active'", [$tenantId]) as $r) {
                $ids[] = (int) $r['user_id'];
            }
        }
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $out = [];
        foreach ($this->rows("SELECT * FROM users WHERE id IN ({$in})", $ids) as $u) {
            if (strtolower((string) ($u['status'] ?? '')) !== 'active') {
                continue;
            }
            if (TenantRemovalService::isTechnicalAccount($u)) {
                continue;
            }
            $email = strtolower(trim((string) ($u['email'] ?? '')));
            if ($email === '' || str_ends_with($email, '@deleted.invalid')) {
                continue;
            }
            if (!empty($u['deleted_at']) || !empty($u['deletion_requested_at'])) {
                continue;
            }
            $out[] = (int) $u['id'];
        }
        sort($out);

        return $out;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, list<string>>
     */
    private function roleSlugsFor(int $tenantId, array $userIds): array
    {
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $out = [];
        $add = static function (array $rows) use (&$out): void {
            foreach ($rows as $r) {
                $out[(int) $r['uid']][] = (string) $r['slug'];
            }
        };
        $add($this->rows(
            "SELECT u.id AS uid, r.slug FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id IN ({$in}) AND r.tenant_id = ?",
            array_merge($userIds, [$tenantId])
        ));
        if ($this->hasTable('user_community_profiles')) {
            $add($this->rows(
                "SELECT p.user_id AS uid, r.slug FROM user_community_profiles p INNER JOIN roles r ON r.id = p.role_id WHERE p.user_id IN ({$in}) AND p.tenant_id = ?",
                array_merge($userIds, [$tenantId])
            ));
        }
        if ($this->hasTable('user_roles')) {
            $add($this->rows(
                "SELECT ur.user_id AS uid, r.slug FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id IN ({$in}) AND r.tenant_id = ?",
                array_merge($userIds, [$tenantId])
            ));
        }
        if ($this->hasTable('tenant_user_roles')) {
            $add($this->rows(
                "SELECT tur.user_id AS uid, r.slug FROM tenant_user_roles tur INNER JOIN roles r ON r.id = tur.role_id WHERE tur.user_id IN ({$in}) AND tur.tenant_id = ?",
                array_merge($userIds, [$tenantId])
            ));
        }

        return $out;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, string>
     */
    private function joinedAt(int $tenantId, array $userIds): array
    {
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $out = [];
        foreach ($this->rows("SELECT id, created_at FROM users WHERE id IN ({$in})", $userIds) as $r) {
            $out[(int) $r['id']] = (string) ($r['created_at'] ?? '');
        }
        if ($this->hasTable('user_community_memberships')) {
            foreach ($this->rows(
                "SELECT user_id, joined_at FROM user_community_memberships WHERE tenant_id = ? AND user_id IN ({$in})",
                array_merge([$tenantId], $userIds)
            ) as $r) {
                if (!empty($r['joined_at'])) {
                    $out[(int) $r['user_id']] = (string) $r['joined_at'];
                }
            }
        }

        return $out;
    }

    private function grantRole(int $userId, int $tenantId, int $roleId): void
    {
        // Passe par le dépôt en production (cache RBAC, rôles d’unité), en SQL direct sinon.
        if (!$this->injectedPdo) {
            try {
                (new \App\Repositories\UserRepository())->addOrganizationRoleIfMissing($userId, $tenantId, $roleId, null);

                return;
            } catch (Throwable) {
            }
        }
        if ($this->hasTable('user_roles')) {
            $exists = $this->rows('SELECT 1 FROM user_roles WHERE user_id = ? AND role_id = ?', [$userId, $roleId]);
            if ($exists === []) {
                $this->pdo()->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$userId, $roleId]);
            }

            return;
        }
        $this->pdo()->prepare('UPDATE users SET role_id = ? WHERE id = ?')->execute([$roleId, $userId]);
    }

    /**
     * @param list<int> $departing
     */
    private function moveOwnerColumn(int $tenantId, array $departing, int $successorId): void
    {
        if ($successorId < 1 || !$this->hasColumn('tenants', 'owner_user_id')) {
            return;
        }
        $in = implode(',', array_fill(0, count($departing), '?'));
        $this->pdo()->prepare("UPDATE tenants SET owner_user_id = ? WHERE id = ? AND owner_user_id IN ({$in})")
            ->execute(array_merge([$successorId, $tenantId], $departing));
    }

    private function announce(int $tenantId, string $successorName): void
    {
        if (!$this->hasTable('tenant_community_feed')) {
            return;
        }
        try {
            $this->pdo()->prepare(
                'INSERT INTO tenant_community_feed (tenant_id, category, title, body) VALUES (?, ?, ?, ?)'
            )->execute([
                $tenantId,
                'owner_handover',
                'Nouveau propriétaire',
                $successorName . ' reprend la communauté : le compte du propriétaire précédent a été supprimé.',
            ]);
        } catch (Throwable) {
        }
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    private function removeCommunity(int $tenantId, string $slug): array
    {
        if ($this->removeCommunity !== null) {
            return ($this->removeCommunity)($tenantId, $slug);
        }
        $removal = new TenantRemovalService($this->pdo);
        $tenant = $this->tenantRow($tenantId);
        if ($tenant !== null && !TenantArchiveState::isArchived($tenant)) {
            $archived = $removal->archive($tenantId, null, 'Suppression du compte de son dernier membre');
            if (!($archived['ok'] ?? false)) {
                return ['ok' => false, 'error' => (string) ($archived['error'] ?? 'archivage refusé')];
            }
        }
        $deleted = $removal->delete($tenantId, $slug);

        return ['ok' => (bool) ($deleted['ok'] ?? false)] + (isset($deleted['error']) ? ['error' => (string) $deleted['error']] : []);
    }

    private function ownerRoleId(int $tenantId): int
    {
        $row = $this->rows(
            'SELECT id FROM roles WHERE tenant_id = ? AND ' . SqlText::equals($this->pdo(), 'slug') . ' LIMIT 1',
            [$tenantId, self::OWNER_ROLE_SLUG]
        );

        return (int) ($row[0]['id'] ?? 0);
    }

    /**
     * @param array<string, mixed> $tenant
     */
    private function isPlaceholder(array $tenant): bool
    {
        return (int) ($tenant['id'] ?? 0) <= 1 || strtolower(trim((string) ($tenant['slug'] ?? ''))) === 'default';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tenantRow(int $tenantId): ?array
    {
        return $this->rows('SELECT * FROM tenants WHERE id = ? LIMIT 1', [$tenantId])[0] ?? null;
    }

    private function userLabel(int $userId): string
    {
        $u = $this->rows('SELECT display_name, callsign, email FROM users WHERE id = ? LIMIT 1', [$userId])[0] ?? [];
        foreach (['display_name', 'callsign'] as $k) {
            $v = trim((string) ($u[$k] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return 'Membre #' . $userId;
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function cleanIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $params = []): array
    {
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($params);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    private function hasTable(string $table): bool
    {
        if (!array_key_exists($table, $this->tables)) {
            try {
                $this->pdo()->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
                $this->tables[$table] = true;
            } catch (Throwable) {
                $this->tables[$table] = false;
            }
        }

        return $this->tables[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $this->columns)) {
            try {
                $this->pdo()->query('SELECT `' . $column . '` FROM `' . $table . '` LIMIT 1');
                $this->columns[$key] = true;
            } catch (Throwable) {
                $this->columns[$key] = false;
            }
        }

        return $this->columns[$key];
    }
}