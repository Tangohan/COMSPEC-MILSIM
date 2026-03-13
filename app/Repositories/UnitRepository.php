<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UnitRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function allForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM units WHERE tenant_id = ? ORDER BY display_order ASC, name ASC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByType(int $tenantId, string $type): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM units WHERE tenant_id = ? AND type = ? ORDER BY display_order ASC, name ASC');
        $stmt->execute([$tenantId, $type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTeams(int $tenantId): array
    {
        return $this->getByType($tenantId, 'team');
    }

    public function getGroups(int $tenantId): array
    {
        return $this->getByType($tenantId, 'group');
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM units WHERE id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getTree(int $tenantId): array
    {
        $all = $this->allForTenant($tenantId);
        $byParent = [];
        foreach ($all as $u) {
            $pid = (int) ($u['parent_id'] ?? 0);
            $byParent[$pid][] = $u;
        }
        return $this->buildTree($byParent, 0);
    }

    private function buildTree(array $byParent, int $parentId): array
    {
        $out = [];
        foreach ($byParent[$parentId] ?? [] as $u) {
            $u['children'] = $this->buildTree($byParent, (int) $u['id']);
            $out[] = $u;
        }
        return $out;
    }

    public function create(int $tenantId, array $data): array
    {
        $slug = $data['slug'] ?? $this->slugify($data['name'] ?? '');
        $stmt = $this->pdo->prepare(
            'INSERT INTO units (tenant_id, parent_id, name, slug, type, code, commander_user_id, display_order, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            $data['name'] ?? '',
            $slug,
            $data['type'] ?? null,
            $data['code'] ?? null,
            isset($data['commander_user_id']) ? (int) $data['commander_user_id'] : null,
            (int) ($data['display_order'] ?? 0),
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->findById($id, $tenantId);
        return $row ?? [];
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $fields = [];
        $params = [];
        $allowed = ['parent_id', 'name', 'slug', 'type', 'code', 'commander_user_id', 'display_order'];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) continue;
            $fields[] = $key . ' = ?';
            $params[] = $key === 'parent_id' || $key === 'commander_user_id' || $key === 'display_order'
                ? ($data[$key] !== '' && $data[$key] !== null ? (int) $data[$key] : null)
                : $data[$key];
        }
        if (empty($fields)) return true;
        $fields[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;
        $stmt = $this->pdo->prepare('UPDATE units SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM units WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    public function slugExists(int $tenantId, string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM units WHERE tenant_id = ? AND slug = ?';
        $params = [$tenantId, $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($name));
        return strtolower(trim($slug, '-') ?: 'unite');
    }
}
