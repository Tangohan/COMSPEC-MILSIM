<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\AdvancementRepository;
use App\Repositories\OrbatBilletRepository;
use App\Repositories\PersonnelAwardRepository;
use App\Repositories\PersonnelEquipmentAssignmentRepository;
use App\Repositories\QualificationAwardRepository;
use App\Support\AdvancementCodes;

/**
 * Dossier de carrière unifié (201 file) : agrège postes, quals, décorations, grades.
 */
final class CareerFileService
{
    public function __construct(
        private AdvancementRepository $grades,
        private QualificationAwardRepository $qualifications,
        private PersonnelAwardRepository $awards,
        private OrbatBilletRepository $billets,
        private PersonnelEquipmentAssignmentRepository $equipment,
        private QualificationTemporalStatusService $temporal,
    ) {
    }

    /**
     * @return list<array{at: string, kind: string, title: string, detail: string, via?: string}>
     */
    public function timeline(int $tenantId, int $userId): array
    {
        $items = [];

        if ($this->grades->tablesReady()) {
            foreach ($this->grades->historyFor($tenantId, $userId) as $row) {
                $items[] = [
                    'at' => substr((string) ($row['obtained_at'] ?? ''), 0, 10),
                    'kind' => 'grade',
                    'title' => trim((string) ($row['label'] ?? $row['grade_label'] ?? 'Grade')),
                    'detail' => 'Grade obtenu',
                    'via' => AdvancementCodes::viaLabel((string) ($row['obtained_via'] ?? '')),
                ];
            }
        }

        try {
            foreach ($this->qualifications->listForUser($userId, $tenantId) as $row) {
                $t = $this->temporal->resolve($row);
                $items[] = [
                    'at' => substr((string) ($row['obtained_at'] ?? ''), 0, 10),
                    'kind' => 'qualification',
                    'title' => trim((string) ($row['definition_name'] ?? $row['qualification_name'] ?? 'Qualification')),
                    'detail' => $t['label'] !== '' ? $t['label'] : 'Qualification enregistrée',
                    'via' => '',
                ];
            }
        } catch (\Throwable) {
        }

        if ($this->awards->schemaReady()) {
            foreach ($this->awards->listForPersonnel($tenantId, $userId) as $row) {
                $items[] = [
                    'at' => substr((string) ($row['awarded_at'] ?? ''), 0, 10),
                    'kind' => 'award',
                    'title' => trim((string) ($row['definition_name'] ?? 'Décoration')),
                    'detail' => trim((string) ($row['citation_text'] ?? $row['authority'] ?? 'Citation')),
                    'via' => trim((string) ($row['authority'] ?? '')),
                ];
            }
        }

        try {
            foreach ($this->billets->listCareerEvents($tenantId, $userId, 80) as $row) {
                $items[] = [
                    'at' => substr((string) ($row['effective_at'] ?? $row['created_at'] ?? ''), 0, 10),
                    'kind' => 'billet',
                    'title' => trim((string) ($row['title'] ?? $row['event_type'] ?? 'Poste')),
                    'detail' => trim((string) ($row['notes'] ?? $row['event_type'] ?? 'Mouvement ORBAT')),
                    'via' => '',
                ];
            }
        } catch (\Throwable) {
        }

        if ($this->equipment->schemaReady()) {
            foreach ($this->equipment->listForPersonnel($tenantId, $userId) as $row) {
                $items[] = [
                    'at' => substr((string) ($row['assigned_at'] ?? ''), 0, 10),
                    'kind' => 'equipment',
                    'title' => trim((string) ($row['definition_name'] ?? 'Matériel')),
                    'detail' => AdvancementCodes::equipmentLabel((string) ($row['status'] ?? ''))
                        . (!empty($row['serial_number']) ? ' · n° ' . $row['serial_number'] : ''),
                    'via' => '',
                ];
            }
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        });

        return $items;
    }
}
