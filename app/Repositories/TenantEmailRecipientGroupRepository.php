<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TenantEmailRecipientGroupRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function tableExists(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_email_recipient_groups' LIMIT 1");
            $ok = $st && (bool) $st->fetchColumn();
        }

        return $ok;
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM tenant_email_recipient_groups WHERE tenant_id = ? ORDER BY name ASC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id, int $tenantId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM tenant_email_recipient_groups WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array{name: string, description?: ?string, definition: array<string, mixed>} $data
     */
    public function create(int $tenantId, int $createdBy, array $data): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO tenant_email_recipient_groups (tenant_id, name, description, definition_json, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $st->execute([
            $tenantId,
            $data['name'],
            $data['description'] ?? null,
            json_encode($data['definition'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $createdBy > 0 ? $createdBy : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array{name?: string, description?: ?string, definition?: array<string, mixed>} $data
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $sets = [];
        $params = [];
        if (isset($data['name'])) {
            $sets[] = 'name = ?';
            $params[] = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $sets[] = 'description = ?';
            $params[] = $data['description'];
        }
        if (isset($data['definition'])) {
            $sets[] = 'definition_json = ?';
            $params[] = json_encode($data['definition'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        if ($sets === []) {
            return true;
        }
        $params[] = $id;
        $params[] = $tenantId;
        $sql = 'UPDATE tenant_email_recipient_groups SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ? AND tenant_id = ? LIMIT 1';

        return $this->pdo->prepare($sql)->execute($params);
    }

    public function delete(int $id, int $tenantId): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $st = $this->pdo->prepare('DELETE FROM tenant_email_recipient_groups WHERE id = ? AND tenant_id = ? LIMIT 1');

        return $st->execute([$id, $tenantId]) && $st->rowCount() > 0;
    }
}
