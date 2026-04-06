<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class EnlistmentCannedMessageRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistment_canned_messages' LIMIT 1");

        return (bool) $stmt?->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, label, body, sort_order, created_at, updated_at
             FROM enlistment_canned_messages WHERE tenant_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findForTenant(int $id, int $tenantId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, label, body, sort_order FROM enlistment_canned_messages WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(int $tenantId, string $label, string $body, int $sortOrder = 0): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO enlistment_canned_messages (tenant_id, label, body, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $label, $body, $sortOrder]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $tenantId, string $label, string $body, int $sortOrder): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE enlistment_canned_messages SET label = ?, body = ?, sort_order = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
        );

        return $stmt->execute([$label, $body, $sortOrder, $id, $tenantId]);
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM enlistment_canned_messages WHERE id = ? AND tenant_id = ?');

        return $stmt->execute([$id, $tenantId]);
    }
}
