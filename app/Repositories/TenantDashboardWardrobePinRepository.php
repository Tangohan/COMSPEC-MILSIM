<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use PDO;

final class TenantDashboardWardrobePinRepository
{
    public const MAX_PINS = 12;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tableReady(): bool
    {
        try {
            SilentSchemaMigration::run(base_path('bootstrap/dashboard_wardrobe_pins_migration.php'), $this->pdo);
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_dashboard_wardrobe_pins' LIMIT 1"
            );

            return (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    public function countForTenant(int $tenantId): int
    {
        if ($tenantId < 1 || !$this->tableReady()) {
            return 0;
        }
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM tenant_dashboard_wardrobe_pins WHERE tenant_id = ?');
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }

    public function countWardrobesForTenant(int $tenantId): int
    {
        if ($tenantId < 1) {
            return 0;
        }
        try {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM arsenal_wardrobes WHERE tenant_id = ?');
            $st->execute([$tenantId]);

            return (int) $st->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOrderedForTenant(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->tableReady()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT p.*, w.name AS wardrobe_name, w.notes AS wardrobe_notes, w.cover_image_path AS wardrobe_cover_path,
                    w.user_id AS wardrobe_user_id, c.name AS collection_name
             FROM tenant_dashboard_wardrobe_pins p
             INNER JOIN arsenal_wardrobes w ON w.id = p.wardrobe_id AND w.tenant_id = p.tenant_id
             LEFT JOIN arsenal_equipment_collections c ON c.id = w.collection_id
             WHERE p.tenant_id = ?
             ORDER BY p.sort_order ASC, p.id ASC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id, int $tenantId): ?array
    {
        if ($id < 1 || $tenantId < 1 || !$this->tableReady()) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM tenant_dashboard_wardrobe_pins WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByWardrobe(int $tenantId, int $wardrobeId): ?array
    {
        if ($tenantId < 1 || $wardrobeId < 1 || !$this->tableReady()) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM tenant_dashboard_wardrobe_pins WHERE tenant_id = ? AND wardrobe_id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $wardrobeId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data): int
    {
        if (!$this->tableReady()) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO tenant_dashboard_wardrobe_pins (
                tenant_id, wardrobe_id, title, badge_label, figure_path, backdrop_path,
                sort_order, created_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $st->execute([
            $tenantId,
            (int) ($data['wardrobe_id'] ?? 0),
            $data['title'] ?? null,
            $data['badge_label'] ?? null,
            $data['figure_path'] ?? null,
            $data['backdrop_path'] ?? null,
            (int) ($data['sort_order'] ?? $this->nextSortOrder($tenantId)),
            $data['created_by'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        if (!$this->tableReady()) {
            return false;
        }
        $allowed = ['wardrobe_id', 'title', 'badge_label', 'figure_path', 'backdrop_path', 'sort_order'];
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
        $st = $this->pdo->prepare(
            'UPDATE tenant_dashboard_wardrobe_pins SET ' . implode(', ', $sets) . ', updated_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        );
        $st->execute($params);

        return $st->rowCount() > 0;
    }

    public function delete(int $id, int $tenantId): bool
    {
        if (!$this->tableReady()) {
            return false;
        }
        $st = $this->pdo->prepare('DELETE FROM tenant_dashboard_wardrobe_pins WHERE id = ? AND tenant_id = ?');
        $st->execute([$id, $tenantId]);

        return $st->rowCount() > 0;
    }

    /**
     * @param list<int> $orderedIds
     */
    public function reorder(int $tenantId, array $orderedIds): void
    {
        if (!$this->tableReady()) {
            return;
        }
        $this->pdo->beginTransaction();
        try {
            $pos = 0;
            $upd = $this->pdo->prepare(
                'UPDATE tenant_dashboard_wardrobe_pins SET sort_order = ?, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?'
            );
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

    private function nextSortOrder(int $tenantId): int
    {
        $st = $this->pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM tenant_dashboard_wardrobe_pins WHERE tenant_id = ?'
        );
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }
}
