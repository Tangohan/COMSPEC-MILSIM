<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use PDO;

final class TenantLoginAccueilImageRepository
{
    public const MAX_IMAGES = 8;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tableReady(): bool
    {
        try {
            SilentSchemaMigration::run(base_path('bootstrap/tenant_login_accueil_images_migration.php'), $this->pdo);
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_login_accueil_images' LIMIT 1"
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
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM tenant_login_accueil_images WHERE tenant_id = ?');
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->tableReady()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM tenant_login_accueil_images
             WHERE tenant_id = ?
             ORDER BY sort_order ASC, id ASC'
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
            'SELECT * FROM tenant_login_accueil_images WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(int $tenantId, string $storagePath, ?string $altText, ?int $createdBy): int
    {
        if ($tenantId < 1 || $storagePath === '' || !$this->tableReady()) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO tenant_login_accueil_images
                (tenant_id, storage_path, sort_order, alt_text, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([
            $tenantId,
            $storagePath,
            $this->nextSortOrder($tenantId),
            $altText,
            $createdBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updatePath(int $id, int $tenantId, string $storagePath): bool
    {
        if ($id < 1 || $tenantId < 1 || $storagePath === '' || !$this->tableReady()) {
            return false;
        }
        $st = $this->pdo->prepare(
            'UPDATE tenant_login_accueil_images
             SET storage_path = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        );
        $st->execute([$storagePath, $id, $tenantId]);

        return $st->rowCount() > 0;
    }

    public function updateAlt(int $id, int $tenantId, ?string $altText): bool
    {
        if ($id < 1 || $tenantId < 1 || !$this->tableReady()) {
            return false;
        }
        $st = $this->pdo->prepare(
            'UPDATE tenant_login_accueil_images
             SET alt_text = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        );
        $st->execute([$altText, $id, $tenantId]);

        return $st->rowCount() > 0;
    }

    public function delete(int $id, int $tenantId): ?array
    {
        $existing = $this->findById($id, $tenantId);
        if ($existing === null) {
            return null;
        }
        $st = $this->pdo->prepare(
            'DELETE FROM tenant_login_accueil_images WHERE id = ? AND tenant_id = ?'
        );
        $st->execute([$id, $tenantId]);

        return $st->rowCount() > 0 ? $existing : null;
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
                'UPDATE tenant_login_accueil_images SET sort_order = ?, updated_at = NOW()
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
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM tenant_login_accueil_images WHERE tenant_id = ?'
        );
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }
}
