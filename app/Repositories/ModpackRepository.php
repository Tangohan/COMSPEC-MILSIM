<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ModpackRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM modpacks WHERE tenant_id = ? ORDER BY updated_at DESC, released_at DESC, id DESC'
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM modpacks WHERE id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['images'] = $this->getImages((int) $row['id']);
        return $row;
    }

    public function findBySlug(int $tenantId, string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM modpacks WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['images'] = $this->getImages((int) $row['id']);
        return $row;
    }

    /** Modpack principal du tenant (le plus récent par updated_at) pour le dashboard. */
    public function getPrimaryForTenant(int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM modpacks WHERE tenant_id = ? ORDER BY updated_at IS NULL, updated_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['images'] = $this->getImages((int) $row['id']);
        return $row;
    }

    public function slugExists(int $tenantId, string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM modpacks WHERE tenant_id = ? AND slug = ?';
        $params = [$tenantId, $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO modpacks (tenant_id, name, slug, url, version, file_path, size, released_at, updated_at, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $data['tenant_id'],
            $data['name'] ?? '',
            $data['slug'] ?? '',
            $data['url'] ?? null,
            $data['version'] ?? null,
            $data['file_path'] ?? null,
            isset($data['size']) ? (int) $data['size'] : null,
            $data['released_at'] ?? null,
            $data['updated_at'] ?? null,
            $data['description'] ?? null,
            isset($data['created_by']) ? (int) $data['created_by'] : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $allowed = ['name', 'slug', 'url', 'version', 'file_path', 'size', 'released_at', 'updated_at', 'description'];
        $fields = [];
        $params = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $fields[] = $key . ' = ?';
            if ($key === 'size') {
                $params[] = $data[$key] !== null && $data[$key] !== '' ? (int) $data[$key] : null;
            } else {
                $params[] = $data[$key];
            }
        }
        if (empty($fields)) {
            return true;
        }
        $params[] = $id;
        $params[] = $tenantId;
        $stmt = $this->pdo->prepare('UPDATE modpacks SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM modpacks WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    public function getImages(int $modpackId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM modpack_images WHERE modpack_id = ? ORDER BY display_order ASC, id ASC');
        $stmt->execute([$modpackId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addImage(int $modpackId, string $filePath, int $displayOrder = 0): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO modpack_images (modpack_id, file_path, display_order) VALUES (?, ?, ?)');
        $stmt->execute([$modpackId, $filePath, $displayOrder]);
    }

    public function deleteImage(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM modpack_images WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function getImageById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT mi.*, m.tenant_id FROM modpack_images mi INNER JOIN modpacks m ON m.id = mi.modpack_id WHERE mi.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function slugify(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($name));
        return strtolower(trim($slug, '-') ?: 'modpack');
    }
}
