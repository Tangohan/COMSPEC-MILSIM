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
            ];
        }
        if ($rows === []) {
            return ['global' => null, 'detail' => []];
        }
        $globalIndex = null;
        foreach ($rows as $i => $r) {
            if (($r['code'] ?? '') === 'tenure_community') {
                $globalIndex = $i;
                break;
            }
        }
        if ($globalIndex === null) {
            $globalIndex = 0;
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
            $detail[] = [
                'label' => $r['label'],
                'formatted' => $r['formatted'],
            ];
        }

        return ['global' => $global, 'detail' => $detail];
    }
}
