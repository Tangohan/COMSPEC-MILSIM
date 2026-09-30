<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\EquipmentCoverStorage;
use App\Support\SilentSchemaMigration;
use App\Support\SqlText;
use PDO;

class EquipmentClassRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listForTenant(int $tenantId): array
    {
        $this->ensureExtras();
        $stmt = $this->pdo->prepare(
            'SELECT * FROM equipment_classes WHERE tenant_id = ? ORDER BY category ASC, name ASC'
        );
        $stmt->execute([$tenantId]);

        return array_map([$this, 'mapClass'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $this->ensureExtras();
        $sql = 'SELECT * FROM equipment_classes WHERE id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapClass($row) : null;
    }

    public function findBySlug(string $slug, int $tenantId): ?array
    {
        $this->ensureExtras();
        $slugEq = SqlText::equals($this->pdo, 'slug');
        $stmt = $this->pdo->prepare('SELECT * FROM equipment_classes WHERE tenant_id = ? AND ' . $slugEq . ' LIMIT 1');
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapClass($row) : null;
    }

    public function slugExists(int $tenantId, string $slug, ?int $excludeId = null): bool
    {
        $slugEq = SqlText::equals($this->pdo, 'slug');
        $sql = 'SELECT 1 FROM equipment_classes WHERE tenant_id = ? AND ' . $slugEq;
        $params = [$tenantId, $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function slugify(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($name));
        return strtolower(trim($slug, '-') ?: 'equipment');
    }

    public function create(array $data): int
    {
        $this->ensureExtras();
        $stmt = $this->pdo->prepare(
            'INSERT INTO equipment_classes (tenant_id, name, slug, category, description, cover_image_path, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            (int) $data['tenant_id'],
            $data['name'] ?? '',
            $data['slug'] ?? '',
            $data['category'] ?? null,
            $data['description'] ?? null,
            $data['cover_image_path'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $this->ensureExtras();
        $allowed = ['name', 'slug', 'category', 'description', 'cover_image_path'];
        $fields = [];
        $params = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $fields[] = $key . ' = ?';
            $params[] = $data[$key];
        }
        if (empty($fields)) {
            return true;
        }
        $params[] = $id;
        $params[] = $tenantId;
        $stmt = $this->pdo->prepare('UPDATE equipment_classes SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?');
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function setCover(int $id, int $tenantId, ?string $path): bool
    {
        $current = $this->findById($id, $tenantId);
        if ($current === null) {
            return false;
        }
        $ok = $this->update($id, $tenantId, ['cover_image_path' => $path]);
        if ($ok && $path !== ($current['cover_image_path'] ?? null)) {
            EquipmentCoverStorage::delete(isset($current['cover_image_path']) ? (string) $current['cover_image_path'] : null);
        }

        return $ok;
    }

    public function delete(int $id, int $tenantId): bool
    {
        $current = $this->findById($id, $tenantId);
        $stmt = $this->pdo->prepare('DELETE FROM equipment_classes WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        if ($stmt->rowCount() > 0 && $current !== null) {
            EquipmentCoverStorage::delete(isset($current['cover_image_path']) ? (string) $current['cover_image_path'] : null);
        }

        return $stmt->rowCount() > 0;
    }

    private function ensureExtras(): void
    {
        try {
            SilentSchemaMigration::run(base_path('bootstrap/equipment_catalog_extras_migration.php'), $this->pdo);
        } catch (\Throwable) {
            // ignore — colonnes optionnelles
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapClass(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['cover_url'] = EquipmentCoverStorage::publicUrl(
            isset($row['cover_image_path']) ? (string) $row['cover_image_path'] : null
        );

        return $row;
    }
}
