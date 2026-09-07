<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;

/**
 * Chaîne de commandement : chef d’unité + organigramme (unité parente).
 * Le N+1 d’un membre est le chef de son unité ; le N+1 d’un chef est le chef de l’unité au-dessus.
 */
final class PersonnelCommandChainService
{
    public function __construct(
        private ?UnitRepository $units = null,
        private ?UserRepository $users = null,
        private ?PersonnelAssignmentRepository $assignments = null,
    ) {
        $this->units ??= new UnitRepository();
        $this->users ??= new UserRepository();
        $this->assignments ??= new PersonnelAssignmentRepository();
    }

    /**
     * Données de l’écran Effectifs « Chaîne ».
     *
     * @return array{
     *   units: list<array<string, mixed>>,
     *   members: list<array<string, mixed>>,
     *   memberOptions: list<array{id:int,label:string}>,
     *   missing_commanders: int,
     *   missing_superiors: int,
     *   without_unit: int
     * }
     */
    public function workspace(int $tenantId): array
    {
        $empty = [
            'units' => [],
            'members' => [],
            'memberOptions' => [],
            'missing_commanders' => 0,
            'missing_superiors' => 0,
            'without_unit' => 0,
        ];
        if ($tenantId < 1) {
            return $empty;
        }

        $units = $this->units->allForTenant($tenantId);
        $unitsById = [];
        foreach ($units as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $unitsById[$id] = $u;
        }
        $meta = $this->units->hierarchyMetaByUnitId($tenantId);
        $membersByUnit = $this->assignments->listActiveMembersByUnitForTenant($tenantId);
        $primaryByUser = $this->assignments->primaryUnitIdByUserForTenant($tenantId);
        $commandedByUser = self::commandedUnitIdsByUser($unitsById);
        $labels = $this->memberLabelsForTenant($tenantId, $unitsById, $membersByUnit);
        $memberOptions = [];
        foreach ($labels as $uid => $label) {
            $memberOptions[] = ['id' => $uid, 'label' => $label];
        }
        usort($memberOptions, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        $unitRows = [];
        $missingCommanders = 0;
        foreach ($unitsById as $id => $u) {
            $parentId = (int) ($u['parent_id'] ?? 0);
            $commanderId = (int) ($u['commander_user_id'] ?? 0);
            if ($commanderId < 1) {
                $missingCommanders++;
            }
            $members = $membersByUnit[$id] ?? [];
            $unitRows[] = [
                'id' => $id,
                'name' => trim((string) ($u['name'] ?? '')),
                'type' => trim((string) ($u['type'] ?? '')),
                'code' => trim((string) ($u['code'] ?? '')),
                'depth' => max(0, (int) (($meta[$id]['depth'] ?? 1) - 1)),
                'path' => (string) ($meta[$id]['path'] ?? ''),
                'command_key' => (string) ($meta[$id]['command_key'] ?? ''),
                'parent_id' => $parentId > 0 ? $parentId : 0,
                'parent_name' => $parentId > 0 ? trim((string) ($unitsById[$parentId]['name'] ?? '')) : '',
                'commander_user_id' => $commanderId > 0 ? $commanderId : 0,
                'commander_label' => $commanderId > 0 ? ($labels[$commanderId] ?? '—') : '',
                'member_count' => count($members),
                'member_ids' => array_values(array_map(static fn (array $m): int => (int) ($m['user_id'] ?? 0), $members)),
            ];
        }
        usort($unitRows, static function (array $a, array $b): int {
            $c = strcmp((string) $a['command_key'], (string) $b['command_key']);

            return $c !== 0 ? $c : strcasecmp((string) $a['name'], (string) $b['name']);
        });

        $memberRows = [];
        foreach ($labels as $userId => $label) {
            $unitId = (int) ($primaryByUser[$userId] ?? 0);
            $chainIds = self::resolveChainIds($userId, $unitsById, $primaryByUser, $commandedByUser);
            $reportsTo = $chainIds[0] ?? 0;
            $commands = [];
            foreach ($commandedByUser[$userId] ?? [] as $cuid) {
                $name = trim((string) ($unitsById[$cuid]['name'] ?? ''));
                if ($name !== '') {
                    $commands[] = $name;
                }
            }
            $memberRows[] = [
                'user_id' => $userId,
                'label' => $label,
                'unit_id' => $unitId,
                'unit_name' => $unitId > 0 ? trim((string) ($unitsById[$unitId]['name'] ?? '')) : '',
                'reports_to_id' => $reportsTo,
                'reports_to_label' => $reportsTo > 0 ? ($labels[$reportsTo] ?? '—') : '',
                'chain_labels' => array_values(array_filter(array_map(
                    static fn (int $id): string => $labels[$id] ?? '',
                    $chainIds
                ))),
                'commands' => $commands,
            ];
        }
        usort($memberRows, static fn (array $a, array $b): int => strcasecmp((string) $a['label'], (string) $b['label']));

        $missingSuperiors = 0;
        $withoutUnit = 0;
        foreach ($memberRows as $row) {
            if ((int) $row['unit_id'] < 1 && ($row['commands'] ?? []) === []) {
                $withoutUnit++;
            }
            if ((int) $row['reports_to_id'] < 1 && (int) $row['unit_id'] > 0) {
                $missingSuperiors++;
            }
        }

        return [
            'units' => $unitRows,
            'members' => $memberRows,
            'memberOptions' => $memberOptions,
            'missing_commanders' => $missingCommanders,
            'missing_superiors' => $missingSuperiors,
            'without_unit' => $withoutUnit,
        ];
    }

    /**
     * Chaîne d’un membre (du N+1 jusqu’au sommet).
     *
     * @return array{reports_to_id:int,reports_to_label:string,chain_labels:list<string>,commands:list<string>,unit_name:string}
     */
    public function chainForUser(int $tenantId, int $userId): array
    {
        $empty = [
            'reports_to_id' => 0,
            'reports_to_label' => '',
            'chain_labels' => [],
            'commands' => [],
            'unit_name' => '',
        ];
        if ($tenantId < 1 || $userId < 1) {
            return $empty;
        }
        $data = $this->workspace($tenantId);
        foreach ($data['members'] as $row) {
            if ((int) ($row['user_id'] ?? 0) === $userId) {
                return [
                    'reports_to_id' => (int) ($row['reports_to_id'] ?? 0),
                    'reports_to_label' => (string) ($row['reports_to_label'] ?? ''),
                    'chain_labels' => is_array($row['chain_labels'] ?? null) ? $row['chain_labels'] : [],
                    'commands' => is_array($row['commands'] ?? null) ? $row['commands'] : [],
                    'unit_name' => (string) ($row['unit_name'] ?? ''),
                ];
            }
        }

        return $empty;
    }

    /**
     * @param array<int|string, mixed> $commanderByUnit unit_id => user_id (0 = aucun)
     * @return array{ok:bool,updated:int,message:string}
     */
    public function saveCommanders(int $tenantId, array $commanderByUnit, array $allowedUserIds): array
    {
        if ($tenantId < 1) {
            return ['ok' => false, 'updated' => 0, 'message' => 'Communauté introuvable.'];
        }
        $allowed = [];
        foreach ($allowedUserIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $allowed[$id] = true;
            }
        }
        $units = $this->units->allForTenant($tenantId);
        $known = [];
        foreach ($units as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id > 0) {
                $known[$id] = (int) ($u['commander_user_id'] ?? 0);
            }
        }
        $updated = 0;
        foreach ($commanderByUnit as $unitKey => $userKey) {
            $unitId = (int) $unitKey;
            if ($unitId < 1 || !array_key_exists($unitId, $known)) {
                continue;
            }
            $commanderId = (int) $userKey;
            $next = $commanderId > 0 ? $commanderId : null;
            $current = $known[$unitId] > 0 ? $known[$unitId] : null;
            if ($next === $current) {
                continue;
            }
            if ($commanderId > 0 && !isset($allowed[$commanderId])) {
                return [
                    'ok' => false,
                    'updated' => 0,
                    'message' => 'Un chef choisi n’appartient pas à cette communauté.',
                ];
            }
            $this->units->update($unitId, $tenantId, ['commander_user_id' => $next]);
            $updated++;
        }

        return [
            'ok' => true,
            'updated' => $updated,
            'message' => $updated > 0
                ? ($updated === 1
                    ? 'Le chef d’une unité a été enregistré.'
                    : 'Les chefs de ' . $updated . ' unités ont été enregistrés.')
                : 'Aucun changement à enregistrer.',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $unitsById
     * @param array<int, int> $primaryUnitByUser
     * @param array<int, list<int>> $commandedByUser
     * @return list<int>
     */
    public static function resolveChainIds(
        int $userId,
        array $unitsById,
        array $primaryUnitByUser,
        array $commandedByUser
    ): array {
        $chain = [];
        $seen = [$userId => true];
        $current = $userId;
        $guard = 0;
        while ($guard < 32) {
            $guard++;
            $next = self::resolveImmediateSuperiorId($current, $unitsById, $primaryUnitByUser, $commandedByUser);
            if ($next === null || $next < 1 || isset($seen[$next])) {
                break;
            }
            $chain[] = $next;
            $seen[$next] = true;
            $current = $next;
        }

        return $chain;
    }

    /**
     * @param array<int, array<string, mixed>> $unitsById
     * @param array<int, int> $primaryUnitByUser
     * @param array<int, list<int>> $commandedByUser
     */
    public static function resolveImmediateSuperiorId(
        int $userId,
        array $unitsById,
        array $primaryUnitByUser,
        array $commandedByUser
    ): ?int {
        if ($userId < 1) {
            return null;
        }
        $startUnitId = self::walkStartUnitId($userId, $unitsById, $primaryUnitByUser, $commandedByUser);
        if ($startUnitId < 1) {
            return null;
        }
        $visited = [];
        $cursor = $startUnitId;
        $guard = 0;
        while ($cursor > 0 && $guard < 32 && !isset($visited[$cursor])) {
            $guard++;
            $visited[$cursor] = true;
            $unit = $unitsById[$cursor] ?? null;
            if (!is_array($unit)) {
                break;
            }
            $commanderId = (int) ($unit['commander_user_id'] ?? 0);
            if ($commanderId > 0 && $commanderId !== $userId) {
                return $commanderId;
            }
            $cursor = (int) ($unit['parent_id'] ?? 0);
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $unitsById
     * @return array<int, list<int>>
     */
    public static function commandedUnitIdsByUser(array $unitsById): array
    {
        $out = [];
        foreach ($unitsById as $id => $unit) {
            $commanderId = (int) ($unit['commander_user_id'] ?? 0);
            if ($commanderId < 1) {
                continue;
            }
            $out[$commanderId][] = (int) $id;
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $unitsById
     * @param array<int, int> $primaryUnitByUser
     * @param array<int, list<int>> $commandedByUser
     */
    public static function walkStartUnitId(
        int $userId,
        array $unitsById,
        array $primaryUnitByUser,
        array $commandedByUser
    ): int {
        $commanded = $commandedByUser[$userId] ?? [];
        if ($commanded !== []) {
            $bestId = 0;
            $bestDepth = PHP_INT_MAX;
            foreach ($commanded as $unitId) {
                $unitId = (int) $unitId;
                if ($unitId < 1 || !isset($unitsById[$unitId])) {
                    continue;
                }
                $depth = self::unitDepth($unitId, $unitsById);
                if ($depth < $bestDepth || ($depth === $bestDepth && ($bestId === 0 || $unitId < $bestId))) {
                    $bestDepth = $depth;
                    $bestId = $unitId;
                }
            }
            if ($bestId > 0) {
                return (int) ($unitsById[$bestId]['parent_id'] ?? 0);
            }
        }

        return (int) ($primaryUnitByUser[$userId] ?? 0);
    }

    /**
     * @param array<int, array<string, mixed>> $unitsById
     */
    public static function unitDepth(int $unitId, array $unitsById): int
    {
        $depth = 0;
        $visited = [];
        $cursor = $unitId;
        while ($cursor > 0 && !isset($visited[$cursor]) && isset($unitsById[$cursor]) && $depth < 32) {
            $visited[$cursor] = true;
            $cursor = (int) ($unitsById[$cursor]['parent_id'] ?? 0);
            $depth++;
        }

        return $depth;
    }

    /**
     * @param array<int, array<string, mixed>> $unitsById
     * @param array<int, list<array<string, mixed>>> $membersByUnit
     * @return array<int, string>
     */
    private function memberLabelsForTenant(int $tenantId, array $unitsById, array $membersByUnit): array
    {
        $need = [];
        foreach ($unitsById as $u) {
            $cid = (int) ($u['commander_user_id'] ?? 0);
            if ($cid > 0) {
                $need[$cid] = true;
            }
        }
        foreach ($membersByUnit as $members) {
            foreach ($members as $m) {
                $uid = (int) ($m['user_id'] ?? 0);
                if ($uid > 0) {
                    $need[$uid] = true;
                }
            }
        }
        $labels = [];
        foreach ($membersByUnit as $members) {
            foreach ($members as $m) {
                $uid = (int) ($m['user_id'] ?? 0);
                if ($uid < 1 || isset($labels[$uid])) {
                    continue;
                }
                $dn = trim((string) ($m['display_name'] ?? ''));
                $cs = trim((string) ($m['callsign'] ?? ''));
                $labels[$uid] = $dn !== '' ? $dn : ($cs !== '' ? $cs : 'Membre');
            }
        }
        $missing = [];
        foreach (array_keys($need) as $uid) {
            if (!isset($labels[$uid])) {
                $missing[] = $uid;
            }
        }
        if ($missing === []) {
            return $labels;
        }
        try {
            $users = $this->users->listForTenant($tenantId, null, 'active', null, null, null, true);
        } catch (\Throwable) {
            $users = [];
        }
        foreach ($users as $u) {
            $uid = (int) ($u['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $need[$uid] = true;
            if (isset($labels[$uid])) {
                continue;
            }
            $labels[$uid] = self::labelFromUserRow($u);
        }
        foreach ($missing as $uid) {
            if (!isset($labels[$uid])) {
                $labels[$uid] = 'Membre';
            }
        }

        return $labels;
    }

    /** @param array<string, mixed> $user */
    public static function labelFromUserRow(array $user): string
    {
        $dn = trim((string) ($user['display_name'] ?? ''));
        if ($dn !== '') {
            return $dn;
        }
        $cs = trim((string) ($user['callsign'] ?? ''));
        if ($cs !== '') {
            return $cs;
        }
        $em = trim((string) ($user['email'] ?? ''));

        return $em !== '' ? $em : 'Membre';
    }
}
