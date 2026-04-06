<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TenantDashboardPinRepository
{
    private PDO $pdo;

    public const MAX_PINS = 30;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function countForTenant(int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tenant_dashboard_pins WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function listOrderedForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tenant_dashboard_pins WHERE tenant_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tenant_dashboard_pins WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data): int
    {
        $nextOrder = $this->nextSortOrder($tenantId);
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_dashboard_pins (
                tenant_id, pin_type, document_category_id, document_id, courrier_document_id,
                external_url, title, notice_body, sort_order, created_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $tenantId,
            $data['pin_type'],
            $data['document_category_id'] ?? null,
            $data['document_id'] ?? null,
            $data['courrier_document_id'] ?? null,
            $data['external_url'] ?? null,
            $data['title'] ?? null,
            $data['notice_body'] ?? null,
            (int) ($data['sort_order'] ?? $nextOrder),
            $data['created_by'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function nextSortOrder(int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM tenant_dashboard_pins WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $allowed = ['pin_type', 'document_category_id', 'document_id', 'courrier_document_id', 'external_url', 'title', 'notice_body', 'sort_order'];
        $sets = [];
        $params = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $sets[] = $key . ' = ?';
            $params[] = $data[$key];
        }
        if ($sets === []) {
            return false;
        }
        $params[] = $id;
        $params[] = $tenantId;
        $sql = 'UPDATE tenant_dashboard_pins SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ? AND tenant_id = ?';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM tenant_dashboard_pins WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param list<int> $orderedIds
     */
    public function reorder(int $tenantId, array $orderedIds): void
    {
        $this->pdo->beginTransaction();
        try {
            $pos = 0;
            $upd = $this->pdo->prepare('UPDATE tenant_dashboard_pins SET sort_order = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
            foreach ($orderedIds as $pid) {
                $pid = (int) $pid;
                if ($pid <= 0) {
                    continue;
                }
                $upd->execute([$pos, $pid, $tenantId]);
                $pos++;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
