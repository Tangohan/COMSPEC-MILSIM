<?php

declare(strict_types=1);

namespace App\Repositories\Courrier;

use App\Core\Database;
use PDO;

class DocumentPresetRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listForTenant(?int $tenantId): array
    {
        $sql = 'SELECT * FROM document_presets WHERE (tenant_id IS NULL OR tenant_id = ?) ORDER BY is_default DESC, name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId ?? 0]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM document_presets WHERE id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND (tenant_id IS NULL OR tenant_id = ?)';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCode(string $code, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM document_presets WHERE code = ?';
        $params = [$code];
        if ($tenantId !== null) {
            $sql .= ' AND (tenant_id IS NULL OR tenant_id = ?)';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDefault(?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM document_presets WHERE is_default = 1';
        $params = [];
        if ($tenantId !== null) {
            $sql .= ' AND (tenant_id IS NULL OR tenant_id = ?)';
            $params[] = $tenantId;
        }
        $sql .= ' ORDER BY tenant_id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO document_presets (tenant_id, name, code, paper_size, orientation, margins_json, typography_json,
                header_config_json, footer_config_json, signature_config_json, layout_config_json, is_system, is_default, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['tenant_id'] ?? null,
            $data['name'],
            $data['code'],
            $data['paper_size'] ?? 'a4',
            $data['orientation'] ?? 'portrait',
            isset($data['margins_json']) ? (is_string($data['margins_json']) ? $data['margins_json'] : json_encode($data['margins_json'])) : null,
            isset($data['typography_json']) ? (is_string($data['typography_json']) ? $data['typography_json'] : json_encode($data['typography_json'])) : null,
            isset($data['header_config_json']) ? (is_string($data['header_config_json']) ? $data['header_config_json'] : json_encode($data['header_config_json'])) : null,
            isset($data['footer_config_json']) ? (is_string($data['footer_config_json']) ? $data['footer_config_json'] : json_encode($data['footer_config_json'])) : null,
            isset($data['signature_config_json']) ? (is_string($data['signature_config_json']) ? $data['signature_config_json'] : json_encode($data['signature_config_json'])) : null,
            isset($data['layout_config_json']) ? (is_string($data['layout_config_json']) ? $data['layout_config_json'] : json_encode($data['layout_config_json'])) : null,
            $data['is_system'] ?? 0,
            $data['is_default'] ?? 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $allowed = ['name', 'code', 'paper_size', 'orientation', 'margins_json', 'typography_json', 'header_config_json',
            'footer_config_json', 'signature_config_json', 'layout_config_json', 'is_default', 'updated_at'];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            $fields[] = "`$k` = ?";
            $params[] = $k === 'updated_at' ? ($data[$k] ?? date('Y-m-d H:i:s')) : (is_array($data[$k]) ? json_encode($data[$k]) : $data[$k]);
        }
        if (empty($fields)) {
            return true;
        }
        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE document_presets SET ' . implode(', ', $fields) . ' WHERE id = ?');
        return $stmt->execute($params);
    }

    public function setDefault(int $id, ?int $tenantId): void
    {
        $this->pdo->prepare('UPDATE document_presets SET is_default = 0 WHERE (tenant_id IS NULL OR tenant_id = ?)')->execute([$tenantId ?? 0]);
        $this->pdo->prepare('UPDATE document_presets SET is_default = 1 WHERE id = ?')->execute([$id]);
    }
}
