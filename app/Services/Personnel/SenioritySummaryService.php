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
}
