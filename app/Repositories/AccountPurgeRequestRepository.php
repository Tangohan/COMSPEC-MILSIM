<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AccountPurgeRequestRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tableExists(): bool
    {
        $st = $this->pdo->query(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'account_purge_requests' LIMIT 1"
        );

        return (bool) ($st && $st->fetchColumn());
    }

    public function findPendingForTarget(int $tenantId, int $targetUserId): ?array
    {
        if (!$this->tableExists() || $tenantId < 1 || $targetUserId < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            "SELECT * FROM account_purge_requests
             WHERE tenant_id = ? AND target_user_id = ? AND status = 'pending'
             ORDER BY id DESC LIMIT 1"
        );
        $st->execute([$tenantId, $targetUserId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        if (!$this->tableExists() || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM account_purge_requests WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(int $tenantId, int $targetUserId, int $requestedBy, ?string $note = null): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO account_purge_requests (tenant_id, target_user_id, requested_by, note, status, created_at)
             VALUES (?, ?, ?, ?, \'pending\', NOW())'
        );
        $st->execute([
            $tenantId,
            $targetUserId,
            $requestedBy,
            $note !== null && trim($note) !== '' ? trim($note) : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPending(int $limit = 50): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $sql = "SELECT r.*,
                       t.name AS tenant_name, t.slug AS tenant_slug,
                       u.email AS target_email, u.display_name AS target_display_name, u.status AS target_status,
                       req.display_name AS requester_display_name, req.email AS requester_email
                FROM account_purge_requests r
                INNER JOIN tenants t ON t.id = r.tenant_id
                LEFT JOIN users u ON u.id = r.target_user_id
                LEFT JOIN users req ON req.id = r.requested_by
                WHERE r.status = 'pending'
                ORDER BY r.created_at ASC
                LIMIT {$limit}";
        $st = $this->pdo->query($sql);
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? $rows : [];
    }

    public function countPending(): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $st = $this->pdo->query("SELECT COUNT(*) FROM account_purge_requests WHERE status = 'pending'");

        return (int) ($st ? $st->fetchColumn() : 0);
    }

    public function resolve(int $id, string $status, int $resolvedBy, ?string $resolutionNote = null): bool
    {
        if (!$this->tableExists() || $id < 1) {
            return false;
        }
        if (!in_array($status, ['approved', 'rejected', 'cancelled'], true)) {
            return false;
        }
        $st = $this->pdo->prepare(
            "UPDATE account_purge_requests
             SET status = ?, resolution_note = ?, resolved_by = ?, resolved_at = NOW(), updated_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );
        $st->execute([
            $status,
            $resolutionNote !== null && trim($resolutionNote) !== '' ? trim($resolutionNote) : null,
            $resolvedBy,
            $id,
        ]);

        return $st->rowCount() > 0;
    }
}
