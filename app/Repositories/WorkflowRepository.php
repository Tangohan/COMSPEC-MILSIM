<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Moteur de circuits : un circuit (workflow_instances) par objet métier existant, et son journal (workflow_events).
 *
 * Aucune donnée métier ici : la demande d'élévation, la mobilité ou le courrier restent dans leur table. SQL portable
 * (MySQL en production, SQLite dans les tests) : les dates sont passées en paramètre, jamais NOW().
 */
class WorkflowRepository
{
    private ?PDO $pdo;

    private ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function tablesReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            $this->pdo()->query('SELECT 1 FROM workflow_instances LIMIT 1');
            $this->pdo()->query('SELECT 1 FROM workflow_events LIMIT 1');
            $this->ready = true;
        } catch (\Throwable) {
            $this->ready = false;
        }

        return $this->ready;
    }

    /** @return array<string, mixed>|null */
    public function findBySubject(int $tenantId, string $subjectType, int $subjectId): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM workflow_instances WHERE tenant_id = ? AND subject_type = ? AND subject_id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $subjectType, $subjectId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $tenantId, int $id): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM workflow_instances WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param list<int> $subjectIds
     * @return array<int, array<string, mixed>> subject_id => circuit
     */
    public function findManyBySubject(int $tenantId, string $subjectType, array $subjectIds): array
    {
        $subjectIds = array_values(array_unique(array_filter(array_map('intval', $subjectIds), static fn (int $id): bool => $id > 0)));
        if ($subjectIds === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($subjectIds), '?'));
        $st = $this->pdo()->prepare(
            'SELECT * FROM workflow_instances WHERE tenant_id = ? AND subject_type = ? AND subject_id IN (' . $ph . ')'
        );
        $st->execute(array_merge([$tenantId, $subjectType], $subjectIds));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['subject_id']] = $row;
        }

        return $out;
    }

    /**
     * @param array{state: string, member_user_id?: int|null, unit_id?: int|null, assignee_user_id?: int|null, opened_by?: int|null, closed?: bool} $data
     */
    public function createInstance(int $tenantId, string $subjectType, int $subjectId, array $data, string $now): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO workflow_instances
                (tenant_id, subject_type, subject_id, state, member_user_id, unit_id, assignee_user_id, opened_by, created_at, updated_at, closed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            $subjectType,
            $subjectId,
            $data['state'],
            self::idOrNull($data['member_user_id'] ?? null),
            self::idOrNull($data['unit_id'] ?? null),
            self::idOrNull($data['assignee_user_id'] ?? null),
            self::idOrNull($data['opened_by'] ?? null),
            $now,
            $now,
            !empty($data['closed']) ? $now : null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array{state?: string, assignee_user_id?: int|null, task_id?: int|null, closed?: bool} $changes
     */
    public function updateInstance(int $tenantId, int $id, array $changes, string $now): void
    {
        $sets = ['updated_at = ?'];
        $params = [$now];
        if (isset($changes['state'])) {
            $sets[] = 'state = ?';
            $params[] = $changes['state'];
        }
        if (array_key_exists('assignee_user_id', $changes)) {
            $sets[] = 'assignee_user_id = ?';
            $params[] = self::idOrNull($changes['assignee_user_id']);
        }
        if (array_key_exists('task_id', $changes)) {
            $sets[] = 'task_id = ?';
            $params[] = self::idOrNull($changes['task_id']);
        }
        if (!empty($changes['closed'])) {
            $sets[] = 'closed_at = ?';
            $params[] = $now;
        }
        $params[] = $tenantId;
        $params[] = $id;
        $this->pdo()->prepare('UPDATE workflow_instances SET ' . implode(', ', $sets) . ' WHERE tenant_id = ? AND id = ?')
            ->execute($params);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function addEvent(
        int $tenantId,
        int $instanceId,
        string $action,
        ?string $fromState,
        ?string $toState,
        ?int $actorUserId,
        string $comment,
        array $payload,
        string $now
    ): void {
        $this->pdo()->prepare(
            'INSERT INTO workflow_events (instance_id, tenant_id, action, from_state, to_state, actor_user_id, comment, payload_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $instanceId,
            $tenantId,
            $action,
            $fromState,
            $toState,
            self::idOrNull($actorUserId),
            $comment !== '' ? $comment : null,
            $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            $now,
        ]);
    }

    /**
     * @param list<int> $instanceIds
     * @return array<int, list<array<string, mixed>>> instance_id => événements, du plus ancien au plus récent
     */
    public function eventsForInstances(int $tenantId, array $instanceIds): array
    {
        $instanceIds = array_values(array_unique(array_filter(array_map('intval', $instanceIds), static fn (int $id): bool => $id > 0)));
        if ($instanceIds === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($instanceIds), '?'));
        $st = $this->pdo()->prepare(
            'SELECT * FROM workflow_events WHERE tenant_id = ? AND instance_id IN (' . $ph . ') ORDER BY id ASC'
        );
        $st->execute(array_merge([$tenantId], $instanceIds));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
            $row['payload'] = is_array($payload) ? $payload : [];
            $out[(int) $row['instance_id']][] = $row;
        }

        return $out;
    }

    private static function idOrNull(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
