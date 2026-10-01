<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Repositories\OrbatBilletRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Organization\MissionDutyService;
use App\Services\Organization\PositionCapabilityCatalog;
use App\Services\Workflow\WorkTaskService;
use App\Support\AthenaTechnicalRoles;

/**
 * Contexte « Mon Service » : grade / unité / poste organique / duty mission / tâches.
 */
final class MemberServiceContextService
{
    public function __construct(
        private UserRepository $users,
        private UnitRepository $units,
        private OrbatBilletRepository $billets,
        private MissionDutyService $missionDuty,
        private WorkTaskService $workTasks,
    ) {}

    /**
     * @return array{
     *   display_name: string,
     *   grade_label: string,
     *   unit_label: string,
     *   organic_post_label: string,
     *   organic_billet_ids: list<int>,
     *   technical_role: string,
     *   technical_role_label: string,
     *   mission_duty: ?array<string, mixed>,
     *   capability_template: string,
     *   capability_label: string,
     *   tasks: list<array<string, mixed>>,
     *   waiting: array{validations: int, messages: int, reports: int}
     * }
     */
    public function build(int $tenantId, int $userId, string $roleSlug = ''): array
    {
        $user = $this->users->findById($userId) ?? [];
        $displayName = trim((string) ($user['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) (($user['callsign'] ?? '') ?: ($user['email'] ?? 'Membre')));
        }

        $gradeLabel = '';
        try {
            if (!empty($user['grade_id'])) {
                $grade = \App\Core\Container::get(\App\Repositories\GradeRepository::class)
                    ->findById((int) $user['grade_id'], $tenantId);
                $gradeLabel = trim((string) ($grade['name'] ?? $grade['label'] ?? ''));
            }
        } catch (\Throwable) {
            $gradeLabel = '';
        }

        $organic = [];
        $billetIds = [];
        $organicLabel = 'Non affecté';
        $unitLabel = '—';
        $capability = '';
        if ($this->billets->schemaReady()) {
            $organic = $this->billets->primaryBilletsForUser($tenantId, $userId);
            if ($organic !== []) {
                $first = $organic[0];
                $organicLabel = trim((string) ($first['billet_title'] ?? '')) ?: 'Poste organique';
                $billetIds = array_values(array_unique(array_filter(array_map(
                    static fn (array $row): int => (int) ($row['billet_id'] ?? 0),
                    $organic
                ), static fn (int $id): bool => $id > 0)));
                $unitId = (int) ($first['unit_id'] ?? 0);
                if ($unitId > 0) {
                    $unit = $this->units->findById($unitId, $tenantId);
                    $unitLabel = trim((string) ($unit['name'] ?? $unit['code'] ?? '')) ?: $unitLabel;
                }
                $billetRow = $this->billets->findById($tenantId, (int) ($first['billet_id'] ?? 0));
                if (is_array($billetRow)) {
                    $capability = trim((string) ($billetRow['capability_template'] ?? ''));
                }
            }
        }

        $missionDuty = $this->missionDuty->currentForUser($tenantId, $userId);
        if (is_array($missionDuty)) {
            $dutyCap = trim((string) ($missionDuty['capability_template'] ?? ''));
            if ($dutyCap !== '') {
                $capability = $dutyCap;
            }
        }

        $tech = AthenaTechnicalRoles::fromLegacySlug($roleSlug !== '' ? $roleSlug : ($this->users->getRoleSlugForUser($userId) ?? 'member'));

        $tasks = $this->workTasks->inboxForUser($tenantId, $userId, $billetIds, $capability !== '' ? [$capability] : []);

        $waiting = [
            'validations' => 0,
            'messages' => 0,
            'reports' => 0,
        ];
        foreach ($tasks as $task) {
            $type = strtolower(trim((string) ($task['type'] ?? '')));
            if (str_contains($type, 'validate') || str_contains($type, 'ack')) {
                $waiting['validations']++;
            } elseif (str_contains($type, 'report') || str_contains($type, 'sitrep') || str_contains($type, 'intrep')) {
                $waiting['reports']++;
            }
        }

        return [
            'display_name' => $displayName,
            'grade_label' => $gradeLabel !== '' ? $gradeLabel : '—',
            'unit_label' => $unitLabel,
            'organic_post_label' => $organicLabel,
            'organic_billet_ids' => $billetIds,
            'technical_role' => $tech,
            'technical_role_label' => AthenaTechnicalRoles::label($tech),
            'mission_duty' => $missionDuty,
            'capability_template' => $capability,
            'capability_label' => $capability !== '' ? PositionCapabilityCatalog::label($capability) : '',
            'tasks' => $tasks,
            'waiting' => $waiting,
        ];
    }
}
