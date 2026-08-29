<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\PersonnelCorrectionRequestsSchema;
use PDO;

final class PersonnelCorrectionRequestRepository
{
    /** @var list<string> */
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        PersonnelCorrectionRequestsSchema::ensure();
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /**
     * @param array<string, mixed> $proposed
     * @param array<string, mixed> $before
     */
    public function create(
        int $tenantId,
        int $targetUserId,
        int $requestedBy,
        array $proposed,
        array $before,
        string $note = ''
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_correction_requests
                (tenant_id, target_user_id, requested_by, note, proposed_json, before_json, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, \'pending\', NOW())'
        );
        $stmt->execute([
            $tenantId,
            $targetUserId,
            $requestedBy,
            $note !== '' ? $note : null,
            json_encode($proposed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM personnel_correction_requests WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function listOpenForTenant(int $tenantId, int $limit = 100): array
    {
        $limit = max(1, min(300, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT r.*,
                    tu.display_name AS target_display_name, tu.email AS target_email,
                    ru.display_name AS requester_display_name, ru.email AS requester_email
             FROM personnel_correction_requests r
             LEFT JOIN users tu ON tu.id = r.target_user_id
             LEFT JOIN users ru ON ru.id = r.requested_by
             WHERE r.tenant_id = ? AND r.status = 'pending'
             ORDER BY r.created_at ASC
             LIMIT {$limit}"
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn (array $row): array => $this->hydrate($row), $rows);
    }

    /** @return list<array<string, mixed>> */
    public function listForTarget(int $tenantId, int $targetUserId, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM personnel_correction_requests
             WHERE tenant_id = ? AND target_user_id = ?
             ORDER BY created_at DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$tenantId, $targetUserId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn (array $row): array => $this->hydrate($row), $rows);
    }

    public function countPendingForTenant(int $tenantId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM personnel_correction_requests WHERE tenant_id = ? AND status = 'pending'"
        );
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    public function hasPendingForTarget(int $tenantId, int $targetUserId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM personnel_correction_requests
             WHERE tenant_id = ? AND target_user_id = ? AND status = 'pending'
             LIMIT 1"
        );
        $stmt->execute([$tenantId, $targetUserId]);

        return (bool) $stmt->fetchColumn();
    }

    public function resolve(
        int $id,
        int $tenantId,
        string $status,
        int $resolvedBy,
        string $resolutionNote = ''
    ): bool {
        if (!in_array($status, ['approved', 'rejected', 'cancelled'], true)) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE personnel_correction_requests
             SET status = ?, resolution_note = ?, resolved_by = ?, resolved_at = NOW(), updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = 'pending'"
        );
        $stmt->execute([
            $status,
            $resolutionNote !== '' ? $resolutionNote : null,
            $resolvedBy,
            $id,
            $tenantId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): array
    {
        $proposed = json_decode((string) ($row['proposed_json'] ?? '{}'), true);
        $before = json_decode((string) ($row['before_json'] ?? '{}'), true);
        $row['proposed'] = is_array($proposed) ? $proposed : [];
        $row['before'] = is_array($before) ? $before : [];

        return $row;
    }
}
