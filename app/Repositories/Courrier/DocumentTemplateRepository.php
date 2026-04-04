<?php

declare(strict_types=1);

namespace App\Repositories\Courrier;

use App\Core\Database;
use PDO;

class DocumentTemplateRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listForTenant(?int $tenantId, bool $activeOnly = true): array
    {
        $sql = 'SELECT t.*, p.name AS preset_name, p.code AS preset_code FROM document_templates t
                LEFT JOIN document_presets p ON p.id = t.preset_id
                WHERE (t.tenant_id IS NULL OR t.tenant_id = ?)';
        $params = [$tenantId ?? 0];
        if ($activeOnly) {
            $sql .= ' AND t.is_active = 1';
        }
        $sql .= ' ORDER BY t.is_system DESC, t.name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT t.*, p.name AS preset_name, p.code AS preset_code FROM document_templates t
                LEFT JOIN document_presets p ON p.id = t.preset_id
                WHERE t.id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND (t.tenant_id IS NULL OR t.tenant_id = ?)';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findBySlug(string $slug, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM document_templates WHERE slug = ?';
        $params = [$slug];
        if ($tenantId !== null) {
            $sql .= ' AND (tenant_id IS NULL OR tenant_id = ?)';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO document_templates (tenant_id, name, slug, category, description, is_system, is_locked, is_active,
                preset_id, structure_json, body_template, created_by, updated_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['tenant_id'] ?? null,
            $data['name'],
            $data['slug'],
            $data['category'] ?? null,
            $data['description'] ?? null,
            $data['is_system'] ?? 0,
            $data['is_locked'] ?? 0,
            $data['is_active'] ?? 1,
            $data['preset_id'] ?? null,
            isset($data['structure_json']) ? (is_string($data['structure_json']) ? $data['structure_json'] : json_encode($data['structure_json'])) : null,
            $data['body_template'] ?? null,
            $data['created_by'] ?? null,
            $data['updated_by'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $allowed = ['name', 'slug', 'category', 'description', 'is_active', 'preset_id', 'structure_json', 'body_template', 'updated_by', 'updated_at'];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            $fields[] = "`$k` = ?";
            $val = $data[$k];
            if (in_array($k, ['structure_json'], true) && is_array($val)) {
                $val = json_encode($val);
            }
            $params[] = $k === 'updated_at' ? ($data[$k] ?? date('Y-m-d H:i:s')) : $val;
        }
        if (empty($fields)) {
            return true;
        }
        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE document_templates SET ' . implode(', ', $fields) . ' WHERE id = ?');
        return $stmt->execute($params);
    }

    public function createVersion(int $templateId, array $structure, ?string $bodyTemplate, ?int $createdBy): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(version_number), 0) + 1 FROM document_template_versions WHERE template_id = ?');
        $stmt->execute([$templateId]);
        $versionNumber = (int) $stmt->fetchColumn();
        $stmt = $this->pdo->prepare('INSERT INTO document_template_versions (template_id, version_number, structure_json, body_template, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$templateId, $versionNumber, json_encode($structure), $bodyTemplate, $createdBy]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getVersions(int $templateId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_template_versions WHERE template_id = ? ORDER BY version_number DESC');
        $stmt->execute([$templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
