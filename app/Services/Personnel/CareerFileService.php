<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\AdvancementRepository;
use App\Repositories\OrbatBilletRepository;
use App\Repositories\PersonnelAwardRepository;
use App\Repositories\PersonnelEquipmentAssignmentRepository;
use App\Repositories\PersonnelServiceHistoryRepository;
use App\Repositories\QualificationAwardRepository;
use App\Support\AdvancementCodes;

/**
 * Dossier de carrière unifié : agrège postes, quals, décorations, grades et journal de service.
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
        private ?PersonnelServiceHistoryRepository $serviceHistory = null,
    ) {
        $this->serviceHistory ??= new PersonnelServiceHistoryRepository();
    }

    /**
     * @return list<array{at: string, kind: string, title: string, detail: string, via?: string, source?: string}>
     */
    public function timeline(int $tenantId, int $userId, int $limit = 80): array
    {
        $items = [];
        $seen = [];

        if ($this->grades->tablesReady()) {
            foreach ($this->grades->historyFor($tenantId, $userId) as $row) {
                $at = substr((string) ($row['obtained_at'] ?? ''), 0, 10);
                $title = trim((string) ($row['label'] ?? $row['grade_label'] ?? 'Grade'));
                $items[] = $this->pushItem($seen, [
                    'at' => $at,
                    'kind' => 'grade',
                    'title' => $title,
                    'detail' => 'Grade obtenu',
                    'via' => AdvancementCodes::viaLabel((string) ($row['obtained_via'] ?? '')),
                    'source' => 'grades',
                ]);
            }
        }

        try {
            foreach ($this->qualifications->listForUser($userId, $tenantId) as $row) {
                $t = $this->temporal->resolve($row);
                $items[] = $this->pushItem($seen, [
                    'at' => substr((string) ($row['obtained_at'] ?? ''), 0, 10),
                    'kind' => 'qualification',
                    'title' => trim((string) ($row['definition_name'] ?? $row['qualification_name'] ?? 'Qualification')),
                    'detail' => $t['label'] !== '' ? $t['label'] : 'Qualification enregistrée',
                    'via' => '',
                    'source' => 'qualifications',
                ]);
            }
        } catch (\Throwable) {
        }

        if ($this->awards->schemaReady()) {
            foreach ($this->awards->listForPersonnel($tenantId, $userId) as $row) {
                $items[] = $this->pushItem($seen, [
                    'at' => substr((string) ($row['awarded_at'] ?? ''), 0, 10),
                    'kind' => 'award',
                    'title' => trim((string) ($row['definition_name'] ?? 'Décoration')),
                    'detail' => trim((string) ($row['citation_text'] ?? $row['authority'] ?? 'Citation')),
                    'via' => trim((string) ($row['authority'] ?? '')),
                    'source' => 'awards',
                ]);
            }
        }

        try {
            foreach ($this->billets->listCareerEvents($tenantId, $userId, 80) as $row) {
                $items[] = $this->pushItem($seen, [
                    'at' => substr((string) ($row['effective_at'] ?? $row['created_at'] ?? ''), 0, 10),
                    'kind' => 'billet',
                    'title' => trim((string) ($row['title'] ?? $row['event_type'] ?? 'Poste')),
                    'detail' => trim((string) ($row['notes'] ?? $row['event_type'] ?? 'Mouvement ORBAT')),
                    'via' => '',
                    'source' => 'billets',
                ]);
            }
        } catch (\Throwable) {
        }

        if ($this->equipment->schemaReady()) {
            foreach ($this->equipment->listForPersonnel($tenantId, $userId) as $row) {
                $items[] = $this->pushItem($seen, [
                    'at' => substr((string) ($row['assigned_at'] ?? ''), 0, 10),
                    'kind' => 'equipment',
                    'title' => trim((string) ($row['definition_name'] ?? 'Matériel')),
                    'detail' => AdvancementCodes::equipmentLabel((string) ($row['status'] ?? ''))
                        . (!empty($row['serial_number']) ? ' · n° ' . $row['serial_number'] : ''),
                    'via' => '',
                    'source' => 'equipment',
                ]);
            }
        }

        try {
            foreach ($this->serviceHistory->listForUser($userId, 60) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $type = trim((string) ($row['event_type'] ?? 'note'));
                $kind = match ($type) {
                    'assignment' => 'assignment',
                    'promotion' => 'promotion',
                    'qualification' => 'qualification',
                    'deployment' => 'deployment',
                    'award' => 'award',
                    'discipline' => 'discipline',
                    default => 'note',
                };
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $detail = trim((string) ($row['description'] ?? ''));
                $reason = trim((string) ($row['reason_label'] ?? ''));
                if ($reason !== '' && $detail !== '' && !str_contains($detail, $reason)) {
                    $detail .= ' · Motif : ' . $reason;
                } elseif ($reason !== '' && $detail === '') {
                    $detail = 'Motif : ' . $reason;
                }
                $items[] = $this->pushItem($seen, [
                    'at' => substr((string) ($row['event_date'] ?? $row['created_at'] ?? ''), 0, 10),
                    'kind' => $kind,
                    'title' => $title,
                    'detail' => $detail,
                    'via' => $reason,
                    'source' => 'service_history',
                ]);
            }
        } catch (\Throwable) {
        }

        $items = array_values(array_filter($items));
        usort($items, static function (array $a, array $b): int {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        });

        return array_slice($items, 0, max(10, min(200, $limit)));
    }

    /**
     * @param array<string, true> $seen
     * @param array{at: string, kind: string, title: string, detail: string, via?: string, source?: string} $item
     * @return array{at: string, kind: string, title: string, detail: string, via?: string, source?: string}|null
     */
    private function pushItem(array &$seen, array $item): ?array
    {
        $key = strtolower(trim(($item['at'] ?? '') . '|' . ($item['kind'] ?? '') . '|' . ($item['title'] ?? '')));
        if ($key === '||' || isset($seen[$key])) {
            return null;
        }
        $seen[$key] = true;

        return $item;
    }
}
