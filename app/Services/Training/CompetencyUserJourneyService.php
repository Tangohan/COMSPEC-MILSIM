<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\CompetencyUserProgressRepository;

/**
 * Prépare les données affichées sur le parcours compétences opérateur.
 *
 * Prérequis : une dépendance de type « PREREQUIS » (table module_dependencies) est satisfaite uniquement si
 * la progression du module requis est au statut COMPLETED. Tout autre statut (y compris EXPIRED,
 * FAILED, absence de ligne) est traité comme non validé pour le déblocage.
 */
final class CompetencyUserJourneyService
{
    private const PHASES = ['ALPHA', 'BRAVO', 'CHARLIE', 'DELTA'];

    public function __construct(
        private CompetencyUserProgressRepository $competencyUserProgressRepository,
    ) {}

    /**
     * @return array{
     *   schema_available: bool,
     *   load_error: bool,
     *   phases: array<string, list<array<string, mixed>>>,
     *   stats: array{by_phase: array<string, array{total: int, completed: int}>, by_status: array<string, int>},
     *   next_actions: list<string>
     * }
     */
    public function buildForUser(int $tenantId, int $userId): array
    {
        if ($tenantId < 1 || $userId < 1) {
            return $this->emptyPayload(schemaAvailable: true, loadError: false);
        }

        if (!$this->competencyUserProgressRepository->competencySchemaAvailable()) {
            return $this->emptyPayload(schemaAvailable: false, loadError: false);
        }

        try {
            $rows = $this->competencyUserProgressRepository->fetchTenantModuleRows($tenantId, $userId);
        } catch (\Throwable) {
            return $this->emptyPayload(schemaAvailable: true, loadError: true);
        }

        if ($rows === []) {
            $empty = $this->emptyPayload(schemaAvailable: true, loadError: false);
            $empty['stats'] = $this->initialStats();

            return $empty;
        }

        $moduleIds = array_map(static fn (array $r): int => (int) ($r['module_id'] ?? 0), $rows);
        $deps = $this->competencyUserProgressRepository->fetchDependenciesForModules($moduleIds);
        $recurrence = $this->competencyUserProgressRepository->fetchRecurrenceByModules($moduleIds);

        $completedByModule = [];
        foreach ($rows as $r) {
            $mid = (int) ($r['module_id'] ?? 0);
            if ($mid < 1) {
                continue;
            }
            if (($r['progress_status'] ?? '') === 'COMPLETED') {
                $completedByModule[$mid] = true;
            }
        }

        $phases = array_fill_keys(self::PHASES, []);
        $stats = $this->initialStats();
        $nextActions = [];

        $now = time();
        $horizon = $now + 30 * 86400;

        foreach ($rows as $row) {
            $moduleId = (int) ($row['module_id'] ?? 0);
            $type = strtoupper((string) ($row['module_type'] ?? 'ALPHA'));
            if (!in_array($type, self::PHASES, true)) {
                $type = 'ALPHA';
            }

            $rawStatus = (string) ($row['progress_status'] ?? '');
            if ($rawStatus === '') {
                $rawStatus = 'NOT_STARTED';
            }

            $stats['by_phase'][$type]['total']++;
            if ($rawStatus === 'COMPLETED') {
                $stats['by_phase'][$type]['completed']++;
            }
            $stats['by_status'][$rawStatus] = ($stats['by_status'][$rawStatus] ?? 0) + 1;

            $missingPrereq = $this->missingPrereqLabels($deps[$moduleId] ?? [], $completedByModule);
            $blocked = $missingPrereq !== [];

            $expiresAt = $row['expires_at'] ?? null;
            $expiresTs = is_string($expiresAt) && $expiresAt !== '' ? strtotime($expiresAt) : false;
            $expiresDisplay = $expiresTs ? date('d/m/Y', (int) $expiresTs) : null;

            $recurrenceHint = $this->buildRecurrenceHint(
                $row,
                $recurrence[$moduleId] ?? null
            );

            $entry = [
                'module_id' => $moduleId,
                'module_code' => (string) ($row['module_code'] ?? ''),
                'module_name' => (string) ($row['module_name'] ?? ''),
                'module_type' => $type,
                'delivery_mode' => (string) ($row['delivery_mode'] ?? ''),
                'is_mandatory' => (int) ($row['is_mandatory'] ?? 0) === 1,
                'progress_status' => $rawStatus,
                'expires_at_display' => $expiresDisplay,
                'recurrence_hint' => $recurrenceHint,
                'blocked_by_prereq' => $blocked,
                'missing_prereq_labels' => $missingPrereq,
            ];

            $phases[$type][] = $entry;

            if ($blocked && $missingPrereq !== []) {
                $label = $entry['module_name'] !== '' ? $entry['module_name'] : $entry['module_code'];
                $nextActions[] = 'Pour « ' . $label . ' », terminer d’abord : ' . implode(', ', $missingPrereq) . '.';
            }

            if ($rawStatus === 'EXPIRED') {
                $label = $entry['module_name'] !== '' ? $entry['module_name'] : $entry['module_code'];
                $nextActions[] = 'Renouveler la formation « ' . $label . ' » (échéance dépassée).';
            }

            if ($expiresTs && $rawStatus === 'COMPLETED' && $expiresTs >= $now && $expiresTs <= $horizon) {
                $label = $entry['module_name'] !== '' ? $entry['module_name'] : $entry['module_code'];
                $nextActions[] = 'Prévoir le renouvellement de « ' . $label . ' » avant le ' . $expiresDisplay . '.';
            }
        }

        $nextActions = array_values(array_unique($nextActions));

        return [
            'schema_available' => true,
            'load_error' => false,
            'phases' => $phases,
            'stats' => $stats,
            'next_actions' => $nextActions,
        ];
    }

    /**
     * @param list<array<string, mixed>> $depRows
     * @param array<int, true> $completedByModule
     * @return list<string>
     */
    private function missingPrereqLabels(array $depRows, array $completedByModule): array
    {
        $out = [];
        foreach ($depRows as $d) {
            if (($d['dependency_type'] ?? '') !== 'PREREQUIS') {
                continue;
            }
            $reqId = (int) ($d['requires_module_id'] ?? 0);
            if ($reqId < 1) {
                continue;
            }
            if (!empty($completedByModule[$reqId])) {
                continue;
            }
            $name = trim((string) ($d['requires_name'] ?? ''));
            $out[] = $name !== '' ? $name : (string) ($d['requires_code'] ?? ('#' . $reqId));
        }

        return array_values(array_unique($out));
    }

    /**
     * @param array<string, mixed> $moduleRow
     * @param ?array<string, mixed> $ruleRow
     */
    private function buildRecurrenceHint(array $moduleRow, ?array $ruleRow): ?string
    {
        $ovType = $moduleRow['recurrence_override_type'] ?? null;
        $ovType = $ovType !== null && $ovType !== '' ? (string) $ovType : null;
        $ovDays = isset($moduleRow['recurrence_override_days']) && $moduleRow['recurrence_override_days'] !== null && $moduleRow['recurrence_override_days'] !== ''
            ? (int) $moduleRow['recurrence_override_days']
            : null;

        if ($ovType !== null) {
            return $this->recurrenceTypeToHint($ovType, $ovDays);
        }

        if ($ruleRow === null) {
            return null;
        }

        $t = (string) ($ruleRow['recurrence_type'] ?? 'NONE');
        $days = isset($ruleRow['interval_days']) ? (int) $ruleRow['interval_days'] : null;

        return $this->recurrenceTypeToHint($t, $days > 0 ? $days : null);
    }

    private function recurrenceTypeToHint(string $type, ?int $intervalDays): ?string
    {
        return match ($type) {
            'NONE' => null,
            'PERIODIC' => $intervalDays !== null && $intervalDays > 0
                ? 'Renouvellement prévu tous les ' . $intervalDays . ' jour' . ($intervalDays > 1 ? 's' : '') . '.'
                : 'Renouvellement périodique prévu.',
            'EVENT_BASED' => 'Renouvellement lié à un événement ou une situation opérationnelle.',
            default => null,
        };
    }

    /**
     * @return array{
     *   schema_available: bool,
     *   load_error: bool,
     *   phases: array<string, list<array<string, mixed>>>,
     *   stats: array{by_phase: array<string, array{total: int, completed: int}>, by_status: array<string, int>},
     *   next_actions: list<string>
     * }
     */
    private function emptyPayload(bool $schemaAvailable, bool $loadError): array
    {
        return [
            'schema_available' => $schemaAvailable,
            'load_error' => $loadError,
            'phases' => array_fill_keys(self::PHASES, []),
            'stats' => $this->initialStats(),
            'next_actions' => [],
        ];
    }

    /**
     * @return array{by_phase: array<string, array{total: int, completed: int}>, by_status: array<string, int>}
     */
    private function initialStats(): array
    {
        $byPhase = [];
        foreach (self::PHASES as $p) {
            $byPhase[$p] = ['total' => 0, 'completed' => 0];
        }

        return [
            'by_phase' => $byPhase,
            'by_status' => [],
        ];
    }
}
