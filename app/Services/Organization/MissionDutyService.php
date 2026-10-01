<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Repositories\MissionDutyAssignmentRepository;
use App\Repositories\OrbatBilletRepository;

/**
 * Affectation mission (duty) distincte du poste organique ORBAT.
 */
final class MissionDutyService
{
    public function __construct(
        private MissionDutyAssignmentRepository $assignments,
        private OrbatBilletRepository $billets,
    ) {}

    public function schemaReady(): bool
    {
        return $this->assignments->schemaReady();
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id?: int, message?: string}
     */
    public function assign(int $tenantId, array $data, int $actorUserId = 0): array
    {
        if (!$this->schemaReady()) {
            return ['ok' => false, 'message' => 'Affectations mission indisponibles.'];
        }
        $userId = (int) ($data['user_id'] ?? 0);
        $title = trim((string) ($data['duty_title'] ?? ''));
        if ($userId < 1 || $title === '') {
            return ['ok' => false, 'message' => 'Membre et poste mission requis.'];
        }
        if ($actorUserId > 0 && empty($data['created_by'])) {
            $data['created_by'] = $actorUserId;
        }
        if (empty($data['capability_template']) && !empty($data['duty_billet_id']) && $this->billets->schemaReady()) {
            $billet = $this->billets->findById($tenantId, (int) $data['duty_billet_id']);
            if (is_array($billet)) {
                $tpl = trim((string) ($billet['capability_template'] ?? ''));
                if ($tpl !== '') {
                    $data['capability_template'] = $tpl;
                }
            }
        }
        $id = $this->assignments->create($tenantId, $data);
        if ($id < 1) {
            return ['ok' => false, 'message' => 'Affectation mission impossible.'];
        }

        return ['ok' => true, 'id' => $id];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentForUser(int $tenantId, int $userId): ?array
    {
        return $this->assignments->findActiveForUser($tenantId, $userId);
    }

    public function end(int $tenantId, int $assignmentId): bool
    {
        return $this->assignments->end($tenantId, $assignmentId);
    }
}
