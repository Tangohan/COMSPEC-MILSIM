<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\SeniorityRepository;

/**
 * Affichage fiche personnel : libellés d’ancienneté calculés via {@see SeniorityEngine}.
 */
final class SenioritySummaryService
{
    public function __construct(
        private SeniorityRepository $seniorityRepository,
        private SeniorityEngine $seniorityEngine,
    ) {}

    /**
     * @return list<array{label: string, formatted: string}>
     */
    public function linesForPersonnelFile(int $tenantId, int $userId): array
    {
        if (!$this->seniorityRepository->schemaReady() || $tenantId < 1 || $userId < 1) {
            return [];
        }
        $defs = $this->seniorityRepository->listVisibleDefinitionsForTenant($tenantId);
        $lines = [];
        foreach ($defs as $def) {
            $id = (int) ($def['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $periods = $this->seniorityRepository->listPeriodsForUserAndDefinition($userId, $id);
            $computed = $this->seniorityEngine->compute($def, $periods, null);
            $label = trim((string) ($def['label'] ?? ''));
            if ($label === '') {
                $label = 'Ancienneté';
            }
            $lines[] = [
                'label' => $label,
                'formatted' => (string) ($computed['formatted'] ?? '—'),
            ];
        }

        return $lines;
    }

    /**
     * Résumé pour la fiche : une entrée « globale » (priorité à l’ancienneté communauté si publiée) + le détail des autres indicateurs visibles.
     *
     * @return array{
     *   global: ?array{formatted: string, basis_label: string, basis_code: string},
     *   detail: list<array{label: string, formatted: string}>
     * }
     */
    public function personnelSenioritySummary(int $tenantId, int $userId): array
    {
        if (!$this->seniorityRepository->schemaReady() || $tenantId < 1 || $userId < 1) {
            return ['global' => null, 'detail' => []];
        }
        $defs = $this->seniorityRepository->listVisibleDefinitionsForTenant($tenantId);
        $rows = [];
        foreach ($defs as $def) {
            $id = (int) ($def['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $periods = $this->seniorityRepository->listPeriodsForUserAndDefinition($userId, $id);
            $computed = $this->seniorityEngine->compute($def, $periods, null);
            $label = trim((string) ($def['label'] ?? ''));
            if ($label === '') {
                $label = 'Ancienneté';
            }
            $rows[] = [
                'code' => trim((string) ($def['code'] ?? '')),
                'label' => $label,
                'formatted' => (string) ($computed['formatted'] ?? '—'),
                'days' => (int) ($computed['days'] ?? 0),
            ];
        }
        if ($rows === []) {
            return ['global' => null, 'detail' => []];
        }
        $globalIndex = null;
        foreach ($rows as $i => $r) {
            if (($r['code'] ?? '') === 'tenure_community' && (int) ($r['days'] ?? 0) > 0) {
                $globalIndex = $i;
                break;
            }
        }
        if ($globalIndex === null) {
            foreach ($rows as $i => $r) {
                if (($r['code'] ?? '') === 'tenure_pre_platform' && (int) ($r['days'] ?? 0) > 0) {
                    $globalIndex = $i;
                    break;
                }
            }
        }
        if ($globalIndex === null) {
            foreach ($rows as $i => $r) {
                if ((int) ($r['days'] ?? 0) > 0) {
                    $globalIndex = $i;
                    break;
                }
            }
        }
        if ($globalIndex === null) {
            return ['global' => null, 'detail' => []];
        }
        $g = $rows[$globalIndex];
        $global = [
            'formatted' => $g['formatted'],
            'basis_label' => $g['label'],
            'basis_code' => $g['code'],
        ];
        $detail = [];
        foreach ($rows as $i => $r) {
            if ($i === $globalIndex) {
                continue;
            }
            $formatted = trim((string) ($r['formatted'] ?? ''));
            if ($formatted === '' || $formatted === '—' || (int) ($r['days'] ?? 0) < 1) {
                continue;
            }
            $detail[] = [
                'label' => $r['label'],
                'formatted' => $formatted,
            ];
        }

        return ['global' => $global, 'detail' => $detail];
    }

    /**
     * Ancienneté réelle (la plus ancienne entre communauté et arrivée avant le site) pour un aperçu tableur.
     *
     * @param list<int> $userIds
     * @param array<int, string> $enlistmentByUser user_id => date d’engagement (Y-m-d) en repli
     * @return array<int, array{
     *   label: string,
     *   days: int,
     *   community_label: string,
     *   community_days: int,
     *   community_start: string,
     *   pre_platform_label: string,
     *   pre_platform_days: int,
     *   pre_platform_start: string
     * }>
     */
    public function dashboardLabelsByUsers(int $tenantId, array $userIds, array $enlistmentByUser = []): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $x): bool => $x > 0)));
        if ($tenantId < 1 || $userIds === [] || !$this->seniorityRepository->schemaReady()) {
            return [];
        }
        $communityDef = $this->seniorityRepository->findDefinitionIdByTenantAndCode($tenantId, 'tenure_community');
        $preDef = $this->seniorityRepository->findDefinitionIdByTenantAndCode($tenantId, 'tenure_pre_platform');
        $communityStarts = $communityDef !== null
            ? $this->seniorityRepository->earliestStartByUsersForDefinition($communityDef, $userIds)
            : [];
        $preStarts = $preDef !== null
            ? $this->seniorityRepository->earliestStartByUsersForDefinition($preDef, $userIds)
            : [];
        $out = [];
        foreach ($userIds as $uid) {
            $communityStart = trim((string) ($communityStarts[$uid] ?? ''));
            if ($communityStart === '') {
                $communityStart = trim((string) ($enlistmentByUser[$uid] ?? ''));
            }
            $preStart = trim((string) ($preStarts[$uid] ?? ''));
            $communityPack = $this->labelFromStartDate($communityStart);
            $prePack = $this->labelFromStartDate($preStart);
            $realStart = $communityStart;
            if ($preStart !== '' && ($realStart === '' || strcmp($preStart, $realStart) < 0)) {
                $realStart = $preStart;
            }
            $realPack = $this->labelFromStartDate($realStart);
            if ($realPack['days'] < 1 && $communityPack['days'] < 1 && $prePack['days'] < 1) {
                continue;
            }
            $out[$uid] = [
                'label' => $realPack['label'],
                'days' => $realPack['days'],
                'community_label' => $communityPack['label'],
                'community_days' => $communityPack['days'],
                'community_start' => $communityStart,
                'pre_platform_label' => $prePack['label'],
                'pre_platform_days' => $prePack['days'],
                'pre_platform_start' => $preStart,
            ];
        }

        return $out;
    }

    /**
     * @return array{label: string, days: int}
     */
    public function labelFromStartDate(?string $start): array
    {
        $start = trim((string) $start);
        if ($start === '' || $start === '0000-00-00') {
            return ['label' => '', 'days' => 0];
        }
        $computed = $this->seniorityEngine->compute(['calc_mode' => 'from_start'], [['start_date' => $start]]);
        $days = (int) ($computed['days'] ?? 0);
        $label = trim((string) ($computed['formatted'] ?? ''));
        if ($days < 1 || $label === '' || $label === '—') {
            return ['label' => '', 'days' => 0];
        }

        return ['label' => $label, 'days' => $days];
    }
}
