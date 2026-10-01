<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Repositories\DutyRosterRepository;
use App\Repositories\WorkTaskRepository;

/**
 * Moteur universel de tâches — assignable à une personne OU à un poste / permanence.
 */
final class WorkTaskService
{
    public function __construct(
        private WorkTaskRepository $tasks,
        private DutyRosterRepository $roster,
    ) {}

    public function schemaReady(): bool
    {
        return $this->tasks->schemaReady();
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id?: int, message?: string}
     */
    public function create(int $tenantId, array $data, int $actorUserId = 0): array
    {
        if (!$this->schemaReady()) {
            return ['ok' => false, 'message' => 'Moteur de tâches indisponible.'];
        }
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'message' => 'Titre de tâche requis.'];
        }
        if ($actorUserId > 0 && empty($data['created_by'])) {
            $data['created_by'] = $actorUserId;
        }

        // Si seule une permanence est ciblée, résoudre le titulaire courant si possible.
        $positionSlug = strtolower(trim((string) ($data['assigned_position_slug'] ?? '')));
        if ($positionSlug !== '' && empty($data['assigned_user_id']) && $this->roster->schemaReady()) {
            $holder = $this->roster->findCurrentHolder($tenantId, $positionSlug);
            if ($holder && (int) ($holder['user_id'] ?? 0) > 0) {
                $data['assigned_user_id'] = (int) $holder['user_id'];
            }
        }

        $id = $this->tasks->create($tenantId, $data);
        if ($id < 1) {
            return ['ok' => false, 'message' => 'Création de la tâche impossible.'];
        }

        return ['ok' => true, 'id' => $id];
    }

    /**
     * @param list<int> $billetIds
     * @param list<string> $positionSlugs
     * @return list<array<string, mixed>>
     */
    public function inboxForUser(int $tenantId, int $userId, array $billetIds = [], array $positionSlugs = []): array
    {
        $rosterSlugs = $this->roster->currentPositionSlugsForUser($tenantId, $userId);
        $merged = array_values(array_unique(array_merge($positionSlugs, $rosterSlugs)));

        return $this->tasks->listOpenForActor($tenantId, $userId, $billetIds, $merged);
    }

    public function acknowledge(int $tenantId, int $taskId): bool
    {
        return $this->tasks->updateStatus($tenantId, $taskId, 'acknowledged');
    }

    public function start(int $tenantId, int $taskId): bool
    {
        return $this->tasks->updateStatus($tenantId, $taskId, 'in_progress');
    }

    public function complete(int $tenantId, int $taskId): bool
    {
        return $this->tasks->updateStatus($tenantId, $taskId, 'completed');
    }
}
