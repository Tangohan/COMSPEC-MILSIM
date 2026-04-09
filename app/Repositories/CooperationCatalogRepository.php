<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CooperationCatalogRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cooperation_catalog_entries' LIMIT 1");

            return (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listByTenantId(int $tenantId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cooperation_catalog_entries WHERE tenant_id = ? ORDER BY sort_order ASC, label ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findByTenantAndSlug(int $tenantId, string $slug): ?array
    {
        if (!$this->tableExists() || $slug === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cooperation_catalog_entries WHERE tenant_id = ? AND slug = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function insert(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cooperation_catalog_entries (tenant_id, slug, label, description, default_priority, checklist_json, sort_order, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            substr(trim((string) ($data['slug'] ?? '')), 0, 64),
            substr(trim((string) ($data['label'] ?? '')), 0, 255),
            isset($data['description']) ? trim((string) $data['description']) : null,
            isset($data['default_priority']) ? substr(trim((string) $data['default_priority']), 0, 24) : null,
            isset($data['checklist_json']) ? (is_string($data['checklist_json']) ? $data['checklist_json'] : json_encode($data['checklist_json'], JSON_UNESCAPED_UNICODE)) : null,
            (int) ($data['sort_order'] ?? 0),
            !empty($data['is_active']) ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];
        foreach (['label', 'description', 'default_priority', 'sort_order', 'is_active', 'checklist_json'] as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            if ($k === 'is_active') {
                $fields[] = 'is_active = ?';
                $params[] = !empty($data[$k]) ? 1 : 0;
            } elseif ($k === 'sort_order') {
                $fields[] = 'sort_order = ?';
                $params[] = (int) $data[$k];
            } elseif ($k === 'checklist_json') {
                $fields[] = 'checklist_json = ?';
                $v = $data[$k];
                $params[] = is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
            } else {
                $fields[] = "`{$k}` = ?";
                $params[] = $data[$k];
            }
        }
        if ($fields === []) {
            return;
        }
        $params[] = $id;
        $this->pdo->prepare(
            'UPDATE cooperation_catalog_entries SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ? LIMIT 1'
        )->execute($params);
    }

    public function delete(int $id, int $tenantId): void
    {
        $this->pdo->prepare(
            'DELETE FROM cooperation_catalog_entries WHERE id = ? AND tenant_id = ? LIMIT 1'
        )->execute([$id, $tenantId]);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cooperation_catalog_entries WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
