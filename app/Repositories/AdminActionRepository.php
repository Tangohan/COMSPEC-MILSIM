<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AdminActionRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function create(array $payload, array $before = [], array $after = []): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_actions (
                tenant_id, actor_user_id, action_type, target_type, target_id, scope, status, reason,
                before_json, after_json, is_undoable, is_compensable, non_reversible_reason, undo_strategy,
                ip_address, session_id, executed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $payload['tenant_id'] ?? null,
            (int) ($payload['actor_user_id'] ?? 0),
            (string) ($payload['action_type'] ?? 'unknown'),
            (string) ($payload['target_type'] ?? 'unknown'),
            isset($payload['target_id']) ? (string) $payload['target_id'] : null,
            (string) ($payload['scope'] ?? 'platform'),
            (string) ($payload['status'] ?? 'applied'),
            $payload['reason'] ?? null,
            $before !== [] ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $after !== [] ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            !empty($payload['is_undoable']) ? 1 : 0,
            !empty($payload['is_compensable']) ? 1 : 0,
            $payload['non_reversible_reason'] ?? null,
            $payload['undo_strategy'] ?? null,
            $payload['ip_address'] ?? null,
            $payload['session_id'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listRecentUndoable(int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT aa.*, u.email AS actor_email
             FROM admin_actions aa
             LEFT JOIN users u ON u.id = aa.actor_user_id
             WHERE aa.is_undoable = 1 AND aa.status = 'applied'
             ORDER BY aa.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{rows: list<array<string,mixed>>, total: int} */
    public function listPaginated(array $filters, int $page = 1, int $perPage = 30): array
    {
        $perPage = max(10, min(200, $perPage));
        $offset = max(0, ($page - 1) * $perPage);
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['action_type'])) {
            $where[] = 'aa.action_type LIKE ?';
            $params[] = '%' . trim((string) $filters['action_type']) . '%';
        }
        if (!empty($filters['actor_id'])) {
            $where[] = 'aa.actor_user_id = ?';
            $params[] = (int) $filters['actor_id'];
        }
        if (!empty($filters['target_type'])) {
            $where[] = 'aa.target_type = ?';
            $params[] = trim((string) $filters['target_type']);
        }

        $whereSql = implode(' AND ', $where);
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM admin_actions aa WHERE {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT aa.*, u.email AS actor_email
             FROM admin_actions aa
             LEFT JOIN users u ON u.id = aa.actor_user_id
             WHERE {$whereSql}
             ORDER BY aa.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total];
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM admin_actions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function createUndoRequest(int $adminActionId, int $actorId, string $reason): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO admin_action_undo (admin_action_id, requested_by_user_id, reason, status, created_at)
             VALUES (?, ?, ?, 'pending', NOW())"
        );
        $stmt->execute([$adminActionId, $actorId, $reason]);

        return (int) $this->pdo->lastInsertId();
    }

    public function markUndoResult(int $undoId, bool $success, string $message): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE admin_action_undo
             SET status = ?, result_message = ?, executed_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$success ? 'executed' : 'failed', $message, $undoId]);
    }

    public function markActionUndone(int $actionId): void
    {
        $stmt = $this->pdo->prepare("UPDATE admin_actions SET status = 'undone' WHERE id = ?");
        $stmt->execute([$actionId]);
    }

    public function createCompensation(int $adminActionId, int $actorId, string $type, array $details): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO admin_action_compensations (admin_action_id, created_by_user_id, compensation_type, details_json, status, created_at)
             VALUES (?, ?, ?, ?, 'applied', NOW())"
        );
        $stmt->execute([
            $adminActionId,
            $actorId,
            $type,
            json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
