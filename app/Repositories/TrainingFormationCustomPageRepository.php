<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TrainingFormationCustomPageRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listByTenant(int $tenantId, int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, slug, title, is_published, sections_json, created_at, updated_at
            FROM training_formation_custom_pages
            WHERE tenant_id = ?
            ORDER BY updated_at DESC
            LIMIT ' . max(1, min(500, $limit))
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM training_formation_custom_pages WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findPublishedBySlug(int $tenantId, string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM training_formation_custom_pages
            WHERE tenant_id = ? AND slug = ? AND is_published = 1
            LIMIT 1'
        );
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function slugExistsForTenant(int $tenantId, string $slug, ?int $exceptId = null): bool
    {
        if ($exceptId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM training_formation_custom_pages WHERE tenant_id = ? AND slug = ? AND id <> ? LIMIT 1'
            );
            $stmt->execute([$tenantId, $slug, $exceptId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM training_formation_custom_pages WHERE tenant_id = ? AND slug = ? LIMIT 1'
            );
            $stmt->execute([$tenantId, $slug]);
        }

        return (bool) $stmt->fetchColumn();
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_formation_custom_pages
            (tenant_id, slug, title, html_body, sections_json, is_published, created_by, updated_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $tenantId,
            $data['slug'],
            $data['title'],
            $data['html_body'],
            array_key_exists('sections_json', $data) ? $data['sections_json'] : null,
            (int) ($data['is_published'] ?? 0),
            $data['created_by'] ?? null,
            $data['updated_by'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $fields = [];
        $params = [];
        foreach (['slug', 'title', 'html_body', 'sections_json', 'is_published', 'updated_by'] as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $fields[] = $col . ' = ?';
            if ($col === 'is_published') {
                $params[] = (int) (bool) $data[$col];
            } else {
                $params[] = $data[$col];
            }
        }
        if ($fields === []) {
            return true;
        }
        $fields[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;
        $sql = 'UPDATE training_formation_custom_pages SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM training_formation_custom_pages WHERE id = ? AND tenant_id = ?');

        return $stmt->execute([$id, $tenantId]);
    }
}
