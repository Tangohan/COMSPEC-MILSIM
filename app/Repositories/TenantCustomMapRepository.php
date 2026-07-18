<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TenantCustomMapRepository
{
    public const MAP_ID_OFFSET = 1_000_000;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listActiveForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tenant_custom_maps
             WHERE tenant_id = ? AND is_active = 1
             ORDER BY label ASC, id ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByIdForTenant(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tenant_custom_maps WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findBySlugForTenant(string $slug, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tenant_custom_maps WHERE slug = ? AND tenant_id = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$slug, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByMapIdForTenant(int $mapId, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tenant_custom_maps WHERE map_id = ? AND tenant_id = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$mapId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(
        int $tenantId,
        int $createdBy,
        string $label,
        string $slug,
        string $imagePath,
        int $imageWidth,
        int $imageHeight
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_custom_maps
                (tenant_id, created_by, map_id, label, slug, image_path, image_width, image_height, is_active)
             VALUES (?, ?, 0, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([$tenantId, $createdBy, $label, $slug, $imagePath, $imageWidth, $imageHeight]);
        $id = (int) $this->pdo->lastInsertId();
        $mapId = self::MAP_ID_OFFSET + $id;
        $upd = $this->pdo->prepare('UPDATE tenant_custom_maps SET map_id = ? WHERE id = ? AND tenant_id = ?');
        $upd->execute([$mapId, $id, $tenantId]);

        return $id;
    }

    public function updateLabel(int $id, int $tenantId, string $label): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tenant_custom_maps SET label = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ? AND is_active = 1'
        );

        return $stmt->execute([$label, $id, $tenantId]) && $stmt->rowCount() > 0;
    }

    public function softDelete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tenant_custom_maps SET is_active = 0, updated_at = NOW() WHERE id = ? AND tenant_id = ? AND is_active = 1'
        );

        return $stmt->execute([$id, $tenantId]) && $stmt->rowCount() > 0;
    }

    public static function operationalMapId(int $rowId): int
    {
        return self::MAP_ID_OFFSET + $rowId;
    }
}
