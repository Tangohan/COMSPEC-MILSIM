<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\UnitRepository;

/**
 * Arbre ORBAT (format consommé par la vue / l’API) à partir des unités en base.
 */
final class OrbatRosterPayload
{
    private const ORBAT_TYPES = ['command', 'alpha', 'bravo', 'support', 'special'];

    /**
     * @param array<int, int> $unitMemberCounts
     * @param array<int, string> $unitCommanderLabels
     * @param array<int, list<array{user_id: int, label: string}>> $unitRosterByUnit
     * @return array<string, mixed>
     */
    public static function normalizeNode(
        array $u,
        array $unitMemberCounts,
        array $unitCommanderLabels,
        array $unitRosterByUnit
    ): array {
        $rawType = (string) ($u['type'] ?? '');
        $type = in_array($rawType, self::ORBAT_TYPES, true) ? $rawType : 'command';
        $uid = (int) ($u['id'] ?? 0);
        $children = [];
        foreach ($u['children'] ?? [] as $c) {
            if (is_array($c)) {
                $children[] = self::normalizeNode($c, $unitMemberCounts, $unitCommanderLabels, $unitRosterByUnit);
            }
        }
        $mission = '—';
        if (!empty(trim((string) ($u['public_blurb'] ?? '')))) {
            $mission = trim((string) $u['public_blurb']);
        }

        return [
            'id' => 'unit-' . $uid,
            'unitId' => $uid,
            'label' => $u['name'] ?? 'Unité',
            'role' => !empty($u['code']) ? (string) $u['code'] : 'Unité',
            'type' => $type,
            'status' => 'active',
            'strength' => (int) ($unitMemberCounts[$uid] ?? 0),
            'leader' => $unitCommanderLabels[$uid] ?? '—',
            'mission' => $mission,
            'commanderUserId' => (int) ($u['commander_user_id'] ?? 0),
            'members' => $unitRosterByUnit[$uid] ?? [],
            'children' => $children,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function buildForTenant(UnitRepository $unitRepository, int $tenantId): ?array
    {
        $tree = $unitRepository->getTree($tenantId);
        if ($tree === []) {
            return null;
        }
        $flat = UnitRepository::flattenTree($tree);
        $memberCounts = $unitRepository->countDistinctMembersByUnitForTenant($tenantId);
        $commanderLabels = $unitRepository->commanderLabelByUnitForTenant($tenantId, $flat);
        $rosterByUnit = $unitRepository->rosterMembersByUnitForTenant($tenantId);

        if (count($tree) === 1) {
            return self::normalizeNode($tree[0], $memberCounts, $commanderLabels, $rosterByUnit);
        }
        $children = [];
        foreach ($tree as $root) {
            $children[] = self::normalizeNode($root, $memberCounts, $commanderLabels, $rosterByUnit);
        }

        return [
            'id' => 'command',
            'unitId' => 0,
            'label' => 'Command',
            'role' => 'Structure organique',
            'type' => 'command',
            'status' => 'active',
            'strength' => 0,
            'leader' => '—',
            'mission' => 'Direction des unités et coordination.',
            'commanderUserId' => 0,
            'members' => [],
            'children' => $children,
        ];
    }
}
