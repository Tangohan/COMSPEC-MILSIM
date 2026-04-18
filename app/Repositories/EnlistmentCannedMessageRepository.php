<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class EnlistmentCannedMessageRepository
{
    private PDO $pdo;
    private static ?bool $hasContextColumn = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistment_canned_messages' LIMIT 1");

        return (bool) $stmt?->fetchColumn();
    }

    private function hasContextColumn(): bool
    {
        if (self::$hasContextColumn === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistment_canned_messages' AND COLUMN_NAME = 'context' LIMIT 1");
            self::$hasContextColumn = (bool) $stmt?->fetchColumn();
        }

        return self::$hasContextColumn;
    }

    /** @return list<array<string,mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $selectContext = $this->hasContextColumn() ? 'context' : "'generic' AS context";
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, label, body, ' . $selectContext . ', sort_order, created_at, updated_at
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
        $selectContext = $this->hasContextColumn() ? 'context' : "'generic' AS context";
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, label, body, ' . $selectContext . ', sort_order FROM enlistment_canned_messages WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(int $tenantId, string $label, string $body, int $sortOrder = 0, string $context = 'generic'): int
    {
        if ($this->hasContextColumn()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO enlistment_canned_messages (tenant_id, label, body, context, sort_order, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$tenantId, $label, $body, $context, $sortOrder]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO enlistment_canned_messages (tenant_id, label, body, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$tenantId, $label, $body, $sortOrder]);
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $tenantId, string $label, string $body, int $sortOrder, string $context = 'generic'): bool
    {
        if ($this->hasContextColumn()) {
            $stmt = $this->pdo->prepare(
                'UPDATE enlistment_canned_messages SET label = ?, body = ?, context = ?, sort_order = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
            );

            return $stmt->execute([$label, $body, $context, $sortOrder, $id, $tenantId]);
        }
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
