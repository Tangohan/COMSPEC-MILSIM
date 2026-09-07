<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use App\Repositories\UnitRepository;
use PDO;
use Throwable;

/**
 * Arbre ORBAT allégé pour le tableau de bord : unités dépliables + portraits.
 */
final class DashboardOrbatTree
{
    /**
     * @return array{
     *   root: array<string, mixed>,
     *   total_units: int,
     *   total_members: int
     * }|null
     */
    public static function buildForTenant(int $tenantId, int $viewerUserId = 0): ?array
    {
        if ($tenantId < 1) {
            return null;
        }
        try {
            $units = \App\Core\Container::get(UnitRepository::class);
            $roster = OrbatRosterPayload::buildForTenant($units, $tenantId, $viewerUserId > 0 ? $viewerUserId : null);
        } catch (Throwable) {
            return null;
        }
        if (!is_array($roster) || $roster === []) {
            return null;
        }

        $userIds = [];
        self::collectUserIds($roster, $userIds);
        $portraits = self::batchPortraits($tenantId, array_keys($userIds));
        $root = self::enrichNode($roster, $portraits);
        if ($root === null) {
            return null;
        }

        return [
            'root' => $root,
            'total_units' => self::countUnits($root),
            'total_members' => self::countUniqueMembers($root),
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, true> $userIds
     */
    private static function collectUserIds(array $node, array &$userIds): void
    {
        $commanderId = (int) ($node['commanderUserId'] ?? 0);
        if ($commanderId > 0) {
            $userIds[$commanderId] = true;
        }
        foreach ($node['members'] ?? [] as $mem) {
            if (!is_array($mem)) {
                continue;
            }
            $uid = (int) ($mem['user_id'] ?? 0);
            if ($uid > 0) {
                $userIds[$uid] = true;
            }
        }
        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                self::collectUserIds($child, $userIds);
            }
        }
    }

    /**
     * @param list<int> $userIds
     * @return array<int, array{label: string, initials: string, photo_url: ?string, role: string}>
     */
    private static function batchPortraits(int $tenantId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }
        try {
            $pdo = Database::getPdo();
        } catch (Throwable) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $hasPortrait = self::columnExists($pdo, 'personnel_profiles', 'character_portrait_path');
        $portraitSelect = $hasPortrait ? 'pp.character_portrait_path' : 'NULL AS character_portrait_path';
        $rankSelect = self::columnExists($pdo, 'personnel_profiles', 'rank_display')
            ? 'pp.rank_display'
            : 'NULL AS rank_display';
        $ppTenant = self::columnExists($pdo, 'personnel_profiles', 'tenant_id');
        $ppJoin = $ppTenant
            ? 'LEFT JOIN personnel_profiles pp ON pp.user_id = u.id AND pp.tenant_id = ?'
            : 'LEFT JOIN personnel_profiles pp ON pp.user_id = u.id';

        $sql = 'SELECT u.id, u.display_name, u.callsign, u.avatar_url, ' . $portraitSelect . ', ' . $rankSelect . '
                FROM users u
                ' . $ppJoin . '
                WHERE u.id IN (' . $placeholders . ')';
        try {
            $st = $pdo->prepare($sql);
            $params = $ppTenant ? array_merge([$tenantId], $userIds) : $userIds;
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $label = trim((string) ($row['display_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['callsign'] ?? ''));
            }
            if ($label === '') {
                $label = '#' . $id;
            }
            $photo = null;
            if (function_exists('personnel_operator_portrait_url')) {
                $photo = personnel_operator_portrait_url($row);
            }
            if ($photo === null || $photo === '') {
                $avatar = trim((string) ($row['avatar_url'] ?? ''));
                if ($avatar !== '' && function_exists('user_media_public_url')) {
                    $photo = user_media_public_url($avatar);
                } elseif ($avatar !== '') {
                    $photo = $avatar;
                }
            }
            $role = trim((string) ($row['rank_display'] ?? ''));
            $initials = function_exists('user_display_initials')
                ? user_display_initials($label, 2)
                : mb_strtoupper(mb_substr($label, 0, 2));
            $out[$id] = [
                'label' => $label,
                'initials' => $initials,
                'photo_url' => is_string($photo) && $photo !== '' ? $photo : null,
                'role' => $role,
            ];
        }

        return $out;
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute([$table, $column]);

            return (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, array{label: string, initials: string, photo_url: ?string, role: string}> $portraits
     * @return array<string, mixed>|null
     */
    private static function enrichNode(array $node, array $portraits): ?array
    {
        $members = [];
        $seen = [];
        foreach ($node['members'] ?? [] as $mem) {
            if (!is_array($mem)) {
                continue;
            }
            $uid = (int) ($mem['user_id'] ?? 0);
            if ($uid < 1 || isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;
            $pack = $portraits[$uid] ?? null;
            $label = trim((string) ($mem['label'] ?? ''));
            if ($label === '' && is_array($pack)) {
                $label = (string) ($pack['label'] ?? '');
            }
            if ($label === '') {
                $label = '#' . $uid;
            }
            $members[] = [
                'user_id' => $uid,
                'label' => $label,
                'initials' => is_array($pack) ? (string) ($pack['initials'] ?? '') : mb_strtoupper(mb_substr($label, 0, 2)),
                'photo_url' => is_array($pack) ? ($pack['photo_url'] ?? null) : null,
                'role' => is_array($pack) ? (string) ($pack['role'] ?? '') : '',
                'href' => url('personnel/' . $uid),
            ];
        }

        $commanderId = (int) ($node['commanderUserId'] ?? 0);
        $commander = null;
        if ($commanderId > 0) {
            $pack = $portraits[$commanderId] ?? null;
            $commander = [
                'user_id' => $commanderId,
                'label' => is_array($pack) ? (string) ($pack['label'] ?? ($node['leader'] ?? '—')) : (string) ($node['leader'] ?? '—'),
                'initials' => is_array($pack) ? (string) ($pack['initials'] ?? '·') : '·',
                'photo_url' => is_array($pack) ? ($pack['photo_url'] ?? null) : null,
                'role' => is_array($pack) ? (string) ($pack['role'] ?? '') : '',
                'href' => url('personnel/' . $commanderId),
            ];
        }

        $children = [];
        foreach ($node['children'] ?? [] as $child) {
            if (!is_array($child)) {
                continue;
            }
            $enriched = self::enrichNode($child, $portraits);
            if ($enriched !== null) {
                $children[] = $enriched;
            }
        }

        return [
            'unit_id' => (int) ($node['unitId'] ?? 0),
            'label' => (string) ($node['label'] ?? 'Unité'),
            'code' => (string) ($node['role'] ?? ''),
            'type' => (string) ($node['type'] ?? 'command'),
            'strength' => (int) ($node['strength'] ?? count($members)),
            'leader' => (string) ($node['leader'] ?? '—'),
            'mission' => (string) ($node['mission'] ?? ''),
            'commander' => $commander,
            'members' => $members,
            'children' => $children,
        ];
    }

    /** @param array<string, mixed> $node */
    private static function countUnits(array $node): int
    {
        $n = ((int) ($node['unit_id'] ?? 0)) > 0 ? 1 : 0;
        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $n += self::countUnits($child);
            }
        }

        return $n;
    }

    /** @param array<string, mixed> $node */
    private static function countUniqueMembers(array $node): int
    {
        $ids = [];
        self::collectMemberIds($node, $ids);

        return count($ids);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, true> $ids
     */
    private static function collectMemberIds(array $node, array &$ids): void
    {
        foreach ($node['members'] ?? [] as $mem) {
            if (!is_array($mem)) {
                continue;
            }
            $uid = (int) ($mem['user_id'] ?? 0);
            if ($uid > 0) {
                $ids[$uid] = true;
            }
        }
        $commander = is_array($node['commander'] ?? null) ? $node['commander'] : null;
        if ($commander !== null) {
            $cid = (int) ($commander['user_id'] ?? 0);
            if ($cid > 0) {
                $ids[$cid] = true;
            }
        }
        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                self::collectMemberIds($child, $ids);
            }
        }
    }
}
